<?php

namespace App\Services\Luvu;

use App\Contracts\NebiusAIContract;
use App\Http\Controllers\Demo\Concerns\DemoSupport;
use App\Mail\PlainHtmlMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * One reminder per (renewal, stage): written by Nebius AI (template fallback), sent as an email to the subscriber
 * and stored on the renewal so the phone preview shows the same text as a push notification.
 *
 * Abuse guards: one send per stage (idempotent), per-merchant hourly cap, valid addresses only, fake demo domains skipped.
 */
final class RenewalReminder
{
    use DemoSupport;

    /**
     * @param  array<string,mixed>  $plan  GraceStagePlanner::plan() for the stage being entered
     * @return array<string,mixed>
     */
    public function deliver(string $merchantId, object $renewal, ?object $sub, array $plan): array
    {
        $this->scopedMerchant = $merchantId;
        $stage = (string) $plan['stage'];
        $meta = json_decode((string) ($renewal->metadata ?? '{}'), true) ?: [];
        if (isset($meta['reminders'][$stage])) {
            return $meta['reminders'][$stage];   // already sent for this stage: never twice
        }

        $name = (string) ($meta['display_name'] ?? Str::before((string) ($renewal->email ?? 'there'), '@'));
        $product = (string) ($meta['product'] ?? 'your subscription');
        [$body, $source] = $this->compose($merchantId, $name, $product, $plan);
        $subject = $this->subject($stage, $product, (string) $plan['label']);

        $email = $this->send($merchantId, (string) ($sub->email ?? $renewal->email ?? ''), $subject, $body, $name, $product, $plan);

        $record = ['stage' => $stage, 'label' => $plan['label'], 'subject' => $subject, 'body' => $body, 'source' => $source,
            'channels' => ['email', 'push'], 'email' => $email, 'at' => now()->toIso8601String()];

        DB::table('transactions')->where('id', $renewal->id)->where('merchant_id', $merchantId)->update([
            'metadata' => json_encode(array_replace_recursive($meta, ['reminders' => [$stage => $record]])), 'updated_at' => now(),
        ]);
        $this->logWebhookEvent('luvupay', 'renewal.reminder_sent', ['transaction_id' => $renewal->id, 'stage' => $stage, 'source' => $source, 'email' => $email['status']]);
        if ($source === 'nebius') {
            $this->logWebhookEvent('nebius', 'ai.renewal_reminder', ['transaction_id' => $renewal->id, 'stage' => $stage]);
        }

        return $record;
    }

    /** @return array{0:string,1:string} body, source */
    private function compose(string $merchantId, string $name, string $product, array $plan): array
    {
        $template = (string) $plan['message'];
        if (! (bool) config('nebius.enabled', true) || trim((string) config('nebius.api_key', '')) === '') {
            return [$template, 'template'];
        }
        try {
            $available = collect($plan['options'])->where('available', true)->pluck('label')->all();
            $res = app(NebiusAIContract::class)->chatText('renewal_reminder', [
                ['role' => 'system', 'content' => 'You write short, warm, honest subscription renewal reminders for LuvuPay. Respect the subscriber\'s freedom to cancel. Never pressure, shame or invent facts. Plain text only, no markdown, no emojis, maximum 55 words. Use only the facts given.'],
                ['role' => 'user', 'content' => json_encode([
                    'subscriber_name' => $name, 'product' => $product, 'stage' => $plan['label'],
                    'next_charge' => number_format($plan['amount_minor'] / 100, 2).' '.$plan['currency'],
                    'current_period_ends' => $plan['expires_at'], 'hours_to_expiry' => $plan['hours_to_expiry'],
                    'options_now_available' => $available,
                    'task' => 'Write the reminder for this stage. Mention the amount of the NEXT charge and when it happens. If options are available, name them in one sentence.',
                ], JSON_UNESCAPED_UNICODE)],
            ], $merchantId);
            $text = trim((string) ($res['content'] ?? ''), " \t\n\r\0\x0B\"");
            if (mb_strlen($text) >= 20) {
                return [Str::limit(strip_tags($text), 600, ''), 'nebius'];
            }
        } catch (\Throwable $e) {
            Log::warning('renewal_reminder.ai_failed', ['err' => $e->getMessage()]);
        }

        return [$template, 'template'];
    }

    private function subject(string $stage, string $product, string $label): string
    {
        return match ($stage) {
            'd7' => "Your {$product} renews in 7 days",
            'd3' => "3 days until your {$product} renewal",
            'h48' => "48 hours until your {$product} renewal",
            'h6' => "Final reminder: {$product} renews in 6 hours",
            'lapsed' => "Your {$product} renewal payment did not go through",
            'grace_open' => "Your options for {$product} are now open",
            default => "{$product}: ".$label,
        };
    }

    /** @return array{status:string,to:?string} */
    private function send(string $merchantId, string $to, string $subject, string $body, string $name, string $product, array $plan): array
    {
        $to = trim($to);
        $masked = $to !== '' ? Str::mask($to, '*', 2, max(1, strpos($to, '@') !== false ? strpos($to, '@') - 2 : 1)) : null;
        if (! (bool) config('luvupay.renewal.reminder_email', true)) {
            return ['status' => 'disabled', 'to' => $masked];
        }
        if (! filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/@(.+\.)?(luvupay\.local|example\.(com|org|net)|invalid|test)$/i', $to)) {
            return ['status' => 'skipped_demo_address', 'to' => $masked];
        }
        if (! RateLimiter::attempt('renewal-reminder:'.$merchantId, max(1, (int) config('luvupay.renewal.reminder_max_per_hour', 30)), fn () => true, 3600)) {
            return ['status' => 'throttled', 'to' => $masked];
        }
        $merchant = (string) (DB::table('merchants')->where('id', $merchantId)->value('name') ?: 'your provider');
        $e = fn (string $v) => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<div style="font-family:Inter,Arial,sans-serif;max-width:520px;margin:0 auto;padding:24px;color:#0f172a">'
            .'<p style="font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin:0 0 12px">'.$e($plan['label']).'</p>'
            .'<h2 style="margin:0 0 12px;font-size:20px">'.$e($subject).'</h2>'
            .'<p style="font-size:15px;line-height:1.6;margin:0 0 16px">'.nl2br($e($body)).'</p>'
            .'<p style="font-size:13px;color:#475569;margin:0 0 4px">Next charge: <strong>'.$e(number_format($plan['amount_minor'] / 100, 2).' '.$plan['currency']).'</strong></p>'
            .'<hr style="border:none;border-top:1px solid #e2e8f0;margin:20px 0">'
            .'<p style="font-size:11px;color:#94a3b8;margin:0">Sent by LuvuPay for '.$e($merchant).' (sandbox simulation). You are receiving this because of a subscription to '.$e($product).'.</p></div>';

        try {
            Mail::to($to)->sendNow(new PlainHtmlMail($subject, $html));

            return ['status' => 'sent', 'to' => $masked];
        } catch (\Throwable $ex) {
            Log::warning('renewal_reminder.mail_failed', ['err' => $ex->getMessage()]);

            return ['status' => 'failed', 'to' => $masked];
        }
    }
}
