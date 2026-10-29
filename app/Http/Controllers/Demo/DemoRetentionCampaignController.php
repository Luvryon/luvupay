<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Demo\Concerns\DemoSupport;
use App\Mail\PlainHtmlMail;
use App\Services\Nebius\RetentionOfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
            'image_data_url' => ['nullable', 'string', 'max:300000'], // client must compress; ~225KB binary
            'cohort' => ['nullable', 'in:failed_and_refund,selected'],
            'transaction_ids' => ['nullable', 'array', 'max:100'],
            'transaction_ids.*' => ['uuid'],
        ]);

        $merchantId = $this->merchantId($request);
        $cohort = $data['cohort'] ?? 'failed_and_refund';

        $q = DB::table('transactions')->where('merchant_id', $merchantId)->where('type', 'charge');
        if ($cohort === 'selected' && ! empty($data['transaction_ids'])) {
            $q->whereIn('id', $data['transaction_ids']);
        } else {
            // failed payments + rows with refund decision / grace held
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
            $html = $this->buildHtml($name, e($bodyText), (int) $data['discount'], $data['image_data_url'] ?? null);

            try {
                Mail::to($email)->send(new PlainHtmlMail($subject, $html));
                $sent++;
                $this->logWebhookEvent('luvupay', 'retention.campaign_sent', [
                    'transaction_id' => $row->id,
                    'email' => $email,
                    'discount' => $data['discount'],
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
            'message' => $sent > 0
                ? "Campaign sent to {$sent} recipient(s)."
                : 'No emails sent. Check MAIL_* config and cohort filters.',
        ]);
    }

    private function buildHtml(string $name, string $body, int $discount, ?string $imageDataUrl): string
    {
        $img = '';
        if ($imageDataUrl && str_starts_with($imageDataUrl, 'data:image/')) {
            $img = '<div style="margin:16px 0"><img src="'.e($imageDataUrl).'" alt="" style="max-width:100%;border-radius:12px"/></div>';
        }

        return '<div style="font-family:system-ui,sans-serif;max-width:560px;margin:0 auto;color:#0f172a">'
            .'<p>Hi <strong>'.$name.'</strong>,</p>'
            .'<p>'.nl2br($body).'</p>'
            .$img
            .'<p><a href="#" style="display:inline-block;background:#5b3df5;color:#fff;text-decoration:none;padding:10px 16px;border-radius:8px;font-weight:600">'
            .'Renew with '.$discount.'% off</a></p>'
            .'<p style="color:#64748b;font-size:12px">Sent by your merchant via LuvuPay Retention Studio. Merchant approved this send.</p>'
            .'</div>';
    }
}
