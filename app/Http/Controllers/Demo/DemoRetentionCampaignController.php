<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Demo\Concerns\DemoSupport;
use App\Mail\PlainHtmlMail;
use App\Services\Nebius\RetentionOfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** POST /api/v1/dashboard/demo/retention-campaign - merchant-approved bulk retention email. */
final class DemoRetentionCampaignController
{
    use DemoSupport;

    public function send(Request $request, RetentionOfferService $offers): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'discount' => ['required', 'integer', 'min:0', 'max:50'],
            'tone' => ['nullable', 'string', 'max:40'],
            'brand_color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'image_data_url' => ['nullable', 'string', 'max:300000'],
            'cohort' => ['nullable', 'in:failed_and_refund,selected'],
            'transaction_ids' => ['nullable', 'array', 'max:100'],
            'transaction_ids.*' => ['uuid'],
        ]);

        $merchantId = $this->merchantId($request);
        $cohort = $data['cohort'] ?? 'failed_and_refund';
        $brandColor = $this->safeHex($data['brand_color'] ?? '#4f46e5');

        $q = DB::table('transactions')->where('merchant_id', $merchantId)->where('type', 'charge');
        if ($cohort === 'selected' && ! empty($data['transaction_ids'])) {
            $q->whereIn('id', $data['transaction_ids']);
        } else {
            $q->where(function ($w) {
                $w->whereIn('status', ['failed', 'held'])
                    ->orWhere('metadata', 'like', '%partial_refund%')
                    ->orWhere('metadata', 'like', '%GRACE%');
            });
        }

        $rows = $q->orderByDesc('created_at')->limit(50)->get(['id', 'email', 'amount_minor', 'currency', 'metadata', 'status']);
        $sent = 0;
        $skipped = 0;
        $errors = [];

        $merchantName = (string) (DB::table('merchants')->where('id', $merchantId)->value('name') ?: 'Your subscription');
        $ctaUrl = rtrim((string) config('app.url'), '/').'/m/'.$merchantId;

        $decoded = $this->decodeImage($data['image_data_url'] ?? null);
        $publicUrl = $this->persistDecodedImage($decoded, $merchantId);

        foreach ($rows as $row) {
            $email = trim((string) $row->email);
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }
            $meta = json_decode($row->metadata ?? '{}', true) ?: [];
            $name = e((string) ($meta['display_name'] ?? explode('@', $email)[0]));
            $subject = str_replace(['—', '–'], ['-', '-'], $data['subject']);
            $bodyText = str_replace(['—', '–'], ['-', '-'], $data['body']);
            $product = e((string) ($meta['product'] ?? 'your plan'));

            $imgSrc = $decoded ? 'cid:campaign-hero.jpg' : null;
            $html = $this->buildHtml(
                $name,
                e($bodyText),
                (int) $data['discount'],
                $imgSrc,
                e($merchantName),
                $product,
                $ctaUrl,
                $brandColor,
            );

            $inline = [];
            if ($decoded) {
                $inline[] = [
                    'cid' => 'campaign-hero',
                    'data' => $decoded['data'],
                    'mime' => $decoded['mime'],
                    'name' => 'campaign-hero.jpg',
                ];
            }

            try {
                Mail::to($email)->send(new PlainHtmlMail($subject, $html, files: [], inline: $inline));
                $sent++;
                $this->logWebhookEvent('luvupay', 'retention.campaign_sent', [
                    'transaction_id' => $row->id,
                    'email' => $email,
                    'discount' => $data['discount'],
                    'brand_color' => $brandColor,
                    'has_image' => $decoded !== null,
                ]);
            } catch (\Throwable $e) {
                $errors[] = ['email' => $email, 'error' => Str::limit($e->getMessage(), 120)];
            }
        }

        return response()->json([
            'ok' => true,
            'sent' => $sent,
            'skipped' => $skipped,
            'candidates' => $rows->count(),
            'errors' => $errors,
            'has_image' => $decoded !== null,
            'image_url' => $publicUrl,
            'brand_color' => $brandColor,
            'message' => $sent > 0
                ? "Campaign sent to {$sent} recipient(s)".($decoded ? ' (with image).' : '.')
                : 'No emails sent. Check MAIL_* config and cohort filters.',
        ]);
    }

    private function safeHex(string $hex): string
    {
        $hex = trim($hex);
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
            return strtolower($hex);
        }

        return '#4f46e5';
    }

    /** Soft fill / border for offer box from brand hex. */
    private function softPair(string $hex): array
    {
        $h = ltrim($hex, '#');
        $r = hexdec(substr($h, 0, 2));
        $g = hexdec(substr($h, 2, 2));
        $b = hexdec(substr($h, 4, 2));

        return [
            'soft' => "rgba({$r},{$g},{$b},0.12)",
            'border' => "rgba({$r},{$g},{$b},0.35)",
        ];
    }

    /** @return array{data:string,mime:string,ext:string}|null */
    private function decodeImage(?string $imageDataUrl): ?array
    {
        if (! $imageDataUrl || ! str_starts_with($imageDataUrl, 'data:image/')) {
            return null;
        }
        if (! preg_match('#^data:(image/(?:jpeg|jpg|png|webp));base64,(.+)$#s', $imageDataUrl, $m)) {
            return null;
        }
        $mime = strtolower($m[1]) === 'image/jpg' ? 'image/jpeg' : strtolower($m[1]);
        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $binary = base64_decode($m[2], true);
        if ($binary === false || strlen($binary) < 32 || strlen($binary) > 2_500_000) {
            return null;
        }

        return ['data' => $binary, 'mime' => $mime, 'ext' => $ext];
    }

    /** @param  array{data:string,mime:string,ext:string}|null  $decoded */
    private function persistDecodedImage(?array $decoded, string $merchantId): ?string
    {
        if (! $decoded) {
            return null;
        }
        try {
            $dir = 'campaign-images/'.$merchantId;
            $name = Str::uuid()->toString().'.'.$decoded['ext'];
            Storage::disk('public')->put($dir.'/'.$name, $decoded['data']);

            return url('/storage/'.$dir.'/'.$name);
        } catch (\Throwable $e) {
            Log::warning('retention.campaign_image_persist_failed', ['err' => $e->getMessage()]);

            return null;
        }
    }

    private function buildHtml(
        string $name,
        string $body,
        int $discount,
        ?string $imgSrc,
        string $merchantName,
        string $product,
        string $ctaUrl,
        string $brandColor,
    ): string {
        $discountLabel = $discount > 0 ? $discount.'% off' : 'special recovery offer';
        $safeCta = e($ctaUrl);
        $color = e($brandColor);
        $pair = $this->softPair($brandColor);
        $soft = e($pair['soft']);
        $border = e($pair['border']);

        $imgBlock = '';
        if ($imgSrc) {
            $imgBlock = '<tr><td style="padding:0 0 20px 0">'
                .'<img src="'.e($imgSrc).'" alt="Campaign" width="520" style="display:block;width:100%;max-width:520px;height:auto;border:0;border-radius:12px">'
                .'</td></tr>';
        }

        $bodyHtml = nl2br($body);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Keep your access</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Inter,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#0f172a">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;padding:28px 12px">
<tr><td align="center">
<table role="presentation" width="560" cellspacing="0" cellpadding="0" style="width:100%;max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0">
  <tr>
    <td style="padding:22px 28px;background:{$color};color:#fff">
      <div style="font-size:13px;opacity:.9;letter-spacing:.04em;text-transform:uppercase">{$merchantName}</div>
      <div style="font-size:22px;font-weight:800;margin-top:6px;line-height:1.25">Your {$product} access can still be saved</div>
    </td>
  </tr>
  <tr>
    <td style="padding:28px">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
        {$imgBlock}
        <tr><td style="font-size:16px;line-height:1.55;color:#0f172a;padding-bottom:12px">
          Hi <strong>{$name}</strong>,
        </td></tr>
        <tr><td style="font-size:15px;line-height:1.65;color:#334155;padding-bottom:16px">
          {$bodyHtml}
        </td></tr>
        <tr><td style="font-size:15px;line-height:1.65;color:#334155;padding-bottom:16px">
          We know payments fail for everyday reasons - an expired card, a bank hold, or a busy week. You do not have to start over. Finish recovery in one step and keep the benefits you already earned.
        </td></tr>
        <tr><td style="padding-bottom:20px">
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:{$soft};border-radius:12px;border:1px solid {$border}">
            <tr><td style="padding:16px 18px">
              <div style="font-size:12px;font-weight:700;color:{$color};text-transform:uppercase;letter-spacing:.03em">Merchant-approved offer</div>
              <div style="font-size:20px;font-weight:800;color:#0f172a;margin-top:4px">{$discountLabel}</div>
              <div style="font-size:13px;color:#475569;margin-top:6px;line-height:1.5">
                Limited recovery window. Renew now to restore access without losing your place.
              </div>
            </td></tr>
          </table>
        </td></tr>
        <tr><td align="center" style="padding:8px 0 22px 0">
          <a href="{$safeCta}"
             style="display:inline-block;background:{$color};color:#ffffff !important;text-decoration:none;padding:14px 28px;border-radius:999px;font-size:16px;font-weight:700;line-height:1.2">
            Renew with {$discountLabel}
          </a>
        </td></tr>
        <tr><td style="font-size:14px;line-height:1.6;color:#64748b;padding-bottom:8px">
          Prefer another option? Reply to this email and we can help with a split payment, a temporary pause, or a safe partial refund where policy allows.
        </td></tr>
        <tr><td style="font-size:12px;line-height:1.5;color:#94a3b8;padding-top:16px;border-top:1px solid #e2e8f0">
          Sent by <strong style="color:#64748b">{$merchantName}</strong> via LuvuPay Retention Studio.
          This message was approved by the merchant before it was sent. If you did not expect it, you can ignore this email.
        </td></tr>
      </table>
    </td>
  </tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
    }
}
