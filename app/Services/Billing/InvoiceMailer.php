<?php

namespace App\Services\Billing;

use App\Http\Controllers\InvoiceController;
use App\Mail\PlainHtmlMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Receipt email with the invoice inline (Bookipi-style) + the PDF attached. */
final class InvoiceMailer
{
    /** @return array{sent:bool,error:?string} */
    public static function sendForTransaction(string $transactionId, string $kind = 'subscription', ?string $to = null): array
    {
        $inv = DB::table('invoices')->where('transaction_id', $transactionId)->first();
        if (! $inv) {
            return ['sent' => false, 'error' => 'invoice not found'];
        }
        $d = InvoiceView::build($inv);
        $to = $to ?: ($d['to']['email'] ?? null);
        if (! $to || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['sent' => false, 'error' => 'no recipient'];
        }

        try {
            $subject = match ($kind) {
                'resale' => 'Your access is ready — invoice '.$d['number'].' from '.$d['from']['name'],
                default => 'Welcome to '.$d['from']['name'].' — invoice '.$d['number'],
            };
            Mail::to($to)->sendNow(new PlainHtmlMail($subject, self::html($d, $kind), [[
                'name' => 'Invoice-'.preg_replace('/[^A-Za-z0-9_-]/', '', (string) $d['number']).'.pdf',
                'data' => InvoicePdf::render($d),
                'mime' => 'application/pdf',
            ]]));

            return ['sent' => true, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('invoice.email_failed', ['to' => $to, 'err' => $e->getMessage()]);

            return ['sent' => false, 'error' => $e->getMessage()];
        }
    }

    /** @param array<string,mixed> $d */
    public static function html(array $d, string $kind): string
    {
        $e = fn ($v) => e((string) $v);
        $logo = $d['from']['logo_url']
            ? '<img src="'.$e(self::absolute($d['from']['logo_url'])).'" alt="'.$e($d['from']['name']).'" width="56" height="56" style="display:block;border-radius:12px;border:0;object-fit:contain">'
            : '<div style="width:56px;height:56px;border-radius:12px;background:#eef2ff;color:#4f46e5;font:800 20px/56px Arial,sans-serif;text-align:center">'.$e($d['from']['initials']).'</div>';
        $viewUrl = InvoiceController::publicUrl((string) $d['id']);
        $pdfUrl = InvoiceController::publicPdfUrl((string) $d['id']);
        $headline = $kind === 'resale' ? 'Your access is ready' : 'Thanks for subscribing';
        $intro = $kind === 'resale'
            ? 'Your purchase is complete and the access has been transferred to you. Your invoice is below and attached as a PDF.'
            : 'Your subscription is active. Here is your invoice; a PDF copy is attached for your records.';

        $rows = '';
        foreach ($d['items'] as $it) {
            $rows .= '<tr><td style="padding:14px 12px;border-bottom:1px solid #e2e8f0;font-size:14px;color:#0f172a;font-weight:600">'.$e($it['description']).'<div style="font-weight:400;font-size:12px;color:#64748b">Qty '.$e($it['qty']).' × '.$e($it['unit']).'</div></td>'
                .'<td align="right" style="padding:14px 12px;border-bottom:1px solid #e2e8f0;font-size:14px;color:#0f172a;font-weight:700">'.$e($it['amount']).'</td></tr>';
        }
        $addr = $d['from']['address'] ? '<div style="font-size:12px;color:#64748b;line-height:1.5">'.implode('<br>', array_map($e, $d['from']['address'])).'</div>' : '';
        $tax = $d['has_tax'] ? '<tr><td style="padding:4px 12px;font-size:13px;color:#64748b">Tax</td><td align="right" style="padding:4px 12px;font-size:13px;color:#0f172a">'.$e($d['tax']).'</td></tr>' : '';

        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            .'<body style="margin:0;padding:0;background:#f1f5f9;font-family:Inter,Segoe UI,Arial,sans-serif">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:28px 12px"><tr><td align="center">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border:1px solid #e2e8f0;border-radius:16px">'
            .'<tr><td style="padding:28px 32px 8px"><table role="presentation" width="100%"><tr><td width="72" valign="top">'.$logo.'</td>'
            .'<td valign="top"><div style="font-size:17px;font-weight:800;color:#0f172a">'.$e($d['from']['name']).'</div>'.$addr.'</td>'
            .'<td align="right" valign="top"><div style="font-size:24px;font-weight:800;color:#4f46e5;letter-spacing:.02em">INVOICE</div><div style="font-size:12px;color:#64748b">#'.$e($d['number']).'</div></td></tr></table></td></tr>'
            .'<tr><td style="padding:12px 32px 0"><h1 style="margin:16px 0 6px;font-size:22px;color:#0f172a">'.$e($headline).', '.$e(explode(' ', trim((string) $d['to']['name']))[0]).'!</h1>'
            .'<p style="margin:0 0 18px;font-size:14px;line-height:1.6;color:#475569">'.$e($intro).'</p></td></tr>'
            .'<tr><td style="padding:0 32px"><table role="presentation" width="100%" style="background:#f8fafc;border-radius:10px"><tr>'
            .'<td style="padding:14px 16px;font-size:12px;color:#64748b">Invoice date<br><b style="font-size:14px;color:#0f172a">'.$e($d['issued']).'</b></td>'
            .'<td style="padding:14px 16px;font-size:12px;color:#64748b">Payment<br><b style="font-size:14px;color:#0f172a">'.$e($d['payment_method']).'</b></td>'
            .'<td align="right" style="padding:14px 16px"><span style="display:inline-block;background:#d1fae5;color:#047857;font-weight:800;font-size:12px;letter-spacing:.08em;padding:4px 12px;border-radius:6px">'.$e($d['status']).'</span></td></tr></table></td></tr>'
            .'<tr><td style="padding:18px 32px 0"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
            .'<tr><td style="background:#4f46e5;color:#fff;font-size:11px;font-weight:700;letter-spacing:.07em;padding:10px 12px;border-radius:8px 0 0 8px">DESCRIPTION</td><td align="right" style="background:#4f46e5;color:#fff;font-size:11px;font-weight:700;letter-spacing:.07em;padding:10px 12px;border-radius:0 8px 8px 0">AMOUNT</td></tr>'
            .$rows.'</table>'
            .'<table role="presentation" width="100%" style="margin-top:8px"><tr><td width="50%"></td><td><table role="presentation" width="100%">'
            .'<tr><td style="padding:4px 12px;font-size:13px;color:#64748b">Subtotal</td><td align="right" style="padding:4px 12px;font-size:13px;color:#0f172a">'.$e($d['subtotal']).'</td></tr>'.$tax
            .'<tr><td style="padding:10px 12px;background:#eef2ff;border-radius:8px 0 0 8px;font-size:15px;font-weight:800;color:#0f172a">Total</td><td align="right" style="padding:10px 12px;background:#eef2ff;border-radius:0 8px 8px 0;font-size:18px;font-weight:800;color:#4f46e5">'.$e($d['total']).'</td></tr>'
            .'<tr><td style="padding:8px 12px 0;font-size:13px;color:#64748b">Balance due</td><td align="right" style="padding:8px 12px 0;font-size:13px;font-weight:700;color:#0f172a">'.$e($d['balance']).'</td></tr>'
            .'</table></td></tr></table></td></tr>'
            .'<tr><td align="center" style="padding:26px 32px 8px"><a href="'.$e($viewUrl).'" style="display:inline-block;background:#4f46e5;color:#fff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 26px;border-radius:10px">View invoice online</a>'
            .' <a href="'.$e($pdfUrl).'" style="display:inline-block;color:#4f46e5;text-decoration:none;font-weight:700;font-size:14px;padding:12px 14px">Download PDF</a></td></tr>'
            .'<tr><td style="padding:18px 32px 28px;border-top:1px solid #e2e8f0;font-size:12px;color:#94a3b8;text-align:center">Paid with '.$e($d['payment_method']).($d['payment_ref'] ? ' · Ref '.$e($d['payment_ref']) : '').'<br>Sent by LuvuPay on behalf of '.$e($d['from']['name']).'</td></tr>'
            .'</table></td></tr></table></body></html>';
    }

    private static function absolute(string $url): string
    {
        return str_starts_with($url, 'http') ? $url : rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
    }
}
