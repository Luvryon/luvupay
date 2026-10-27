<?php

namespace App\Services\Payout;

use App\Mail\PlainHtmlMail;
use App\Services\Billing\InvoiceView;
use App\Support\MerchantBranding;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Withdrawal receipt sent to the merchant's email: amounts, PayPal fee, net, destination and PayPal batch id. */
final class PayoutMailer
{
    /**
     * @param  array{gross_minor:int,fee_minor:int,net_minor:int,currency:string,batch_id:?string,status:string,receiver_masked:string,simulated:bool,ledger_id:string}  $p
     * @return array{sent:bool,to:?string,error:?string}
     */
    public static function sendWithdrawalReceipt(string $merchantId, array $p): array
    {
        $m = DB::table('merchants')->where('id', $merchantId)->first();
        if (! $m) {
            return ['sent' => false, 'to' => null, 'error' => 'merchant not found', 'recipients' => []];
        }

        $candidates = array_filter([
            trim((string) ($m->email ?? '')),
            trim((string) ($m->payout_email ?? '')),
        ]);
        $recipients = [];
        foreach ($candidates as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[$email] = true;
            }
        }
        $recipients = array_keys($recipients);

        if ($recipients === []) {
            return ['sent' => false, 'to' => null, 'error' => 'merchant has no email', 'recipients' => []];
        }

        $subject = 'Withdrawal receipt — '.InvoiceView::money($p['net_minor'], $p['currency']).' to PayPal';
        $body = self::html($m, $p);
        $sentTo = [];
        $error = null;

        try {
            foreach ($recipients as $to) {
                Mail::to($to)->sendNow(new PlainHtmlMail($subject, $body));
                $sentTo[] = $to;
            }

            return [
                'sent' => $sentTo !== [],
                'to' => $sentTo[0] ?? null,
                'recipients' => $sentTo,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('payout.receipt_email_failed', ['err' => $e->getMessage(), 'tried' => $recipients]);

            return [
                'sent' => $sentTo !== [],
                'to' => $sentTo[0] ?? null,
                'recipients' => $sentTo,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Structured receipt for in-app display / download (PayPal already has the payout; this is the merchant-facing copy).
     *
     * @param  array<string,mixed>  $p
     * @return array<string,mixed>
     */
    public static function receiptPayload(string $merchantId, array $p): array
    {
        $m = DB::table('merchants')->where('id', $merchantId)->first();
        $currency = (string) ($p['currency'] ?? 'USD');
        $money = fn (int $v) => InvoiceView::money($v, $currency);

        return [
            'title' => 'Withdrawal receipt',
            'merchant_name' => $m->name ?? 'Merchant',
            'gross' => $money((int) $p['gross_minor']),
            'fee' => $money((int) $p['fee_minor']),
            'net' => $money((int) $p['net_minor']),
            'gross_minor' => (int) $p['gross_minor'],
            'fee_minor' => (int) $p['fee_minor'],
            'net_minor' => (int) $p['net_minor'],
            'currency' => $currency,
            'batch_id' => $p['batch_id'] ?? null,
            'status' => $p['status'] ?? 'SUCCESS',
            'receiver' => $p['receiver_masked'] ?? null,
            'simulated' => (bool) ($p['simulated'] ?? false),
            'ledger_id' => $p['ledger_id'] ?? null,
            'issued_at' => now()->toIso8601String(),
            'html' => $m ? self::html($m, $p) : '',
        ];
    }

    /** @param array<string,mixed> $p */
    public static function html(object $m, array $p): string
    {
        $e = fn ($v) => e((string) $v);
        $money = fn (int $v) => InvoiceView::money($v, $p['currency']);
        $logoUrl = MerchantBranding::logoUrl($m);
        $logo = $logoUrl
            ? '<img src="'.$e($logoUrl).'" alt="" width="48" height="48" style="display:block;border-radius:10px;object-fit:contain">'
            : '<div style="width:48px;height:48px;border-radius:10px;background:#eef2ff;color:#4f46e5;font:800 17px/48px Arial,sans-serif;text-align:center">'.$e(MerchantBranding::initials((string) $m->name)).'</div>';
        $done = strtolower($p['status']) === 'success';
        $badge = $done ? ['#d1fae5', '#047857', 'COMPLETED'] : ['#fef3c7', '#b45309', 'PROCESSING'];
        $note = $p['simulated']
            ? '<p style="margin:16px 0 0;font-size:12px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 12px">Sandbox note: this withdrawal was recorded as a simulation, so no real money moved.</p>'
            : '<p style="margin:16px 0 0;font-size:12px;color:#64748b">PayPal also emails its own payment notification to your PayPal account.</p>';

        return '<!DOCTYPE html><html><body style="margin:0;padding:28px 12px;background:#f1f5f9;font-family:Inter,Segoe UI,Arial,sans-serif">'
            .'<table role="presentation" width="100%"><tr><td align="center"><table role="presentation" width="100%" style="max-width:560px;background:#fff;border:1px solid #e2e8f0;border-radius:16px"><tr><td style="padding:28px 32px">'
            .'<table role="presentation" width="100%"><tr><td width="60" valign="top">'.$logo.'</td><td valign="top"><div style="font-size:16px;font-weight:800;color:#0f172a">'.$e($m->name).'</div><div style="font-size:12px;color:#64748b">Withdrawal receipt</div></td>'
            .'<td align="right" valign="top"><span style="display:inline-block;background:'.$badge[0].';color:'.$badge[1].';font-weight:800;font-size:11px;letter-spacing:.08em;padding:4px 10px;border-radius:6px">'.$badge[2].'</span></td></tr></table>'
            .'<h1 style="margin:22px 0 4px;font-size:26px;color:#0f172a">'.$e($money($p['net_minor'])).'</h1>'
            .'<p style="margin:0 0 20px;font-size:14px;color:#475569">sent to your PayPal account <b>'.$e($p['receiver_masked']).'</b></p>'
            .'<table role="presentation" width="100%" style="border-top:1px solid #e2e8f0">'
            .self::row('Wallet withdrawal', $money($p['gross_minor']))
            .self::row('PayPal fee', '-'.$money($p['fee_minor']), '#b45309')
            .'<tr><td style="padding:12px 0;border-top:1px solid #e2e8f0;font-size:14px;font-weight:800;color:#0f172a">You receive</td><td align="right" style="padding:12px 0;border-top:1px solid #e2e8f0;font-size:16px;font-weight:800;color:#4f46e5">'.$e($money($p['net_minor'])).'</td></tr>'
            .'</table>'
            .'<table role="presentation" width="100%" style="margin-top:14px;background:#f8fafc;border-radius:10px"><tr><td style="padding:12px 14px;font-size:12px;color:#64748b;line-height:1.7">'
            .'PayPal payout batch<br><b style="color:#0f172a;font-family:monospace">'.$e($p['batch_id'] ?: '—').'</b><br>'
            .'Reference<br><b style="color:#0f172a;font-family:monospace">'.$e(substr($p['ledger_id'], 0, 8)).'</b><br>'
            .'Date<br><b style="color:#0f172a">'.$e(Carbon::now()->format('d M Y, H:i').' UTC').'</b></td></tr></table>'
            .$note
            .'<p style="margin:18px 0 0;font-size:11px;color:#94a3b8">The PayPal fee is charged to your LuvuPay wallet, not to the platform. Sent by LuvuPay.</p>'
            .'</td></tr></table></td></tr></table></body></html>';
    }

    private static function row(string $k, string $v, string $color = '#0f172a'): string
    {
        return '<tr><td style="padding:10px 0;font-size:14px;color:#64748b">'.e($k).'</td><td align="right" style="padding:10px 0;font-size:14px;color:'.$color.'">'.e($v).'</td></tr>';
    }
}
