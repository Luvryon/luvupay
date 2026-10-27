<?php

namespace App\Services\Billing;

use App\Support\MerchantBranding;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** One view-model for the invoice page, the PDF and the receipt email (Bookipi-style layout). */
final class InvoiceView
{
    public static function money(int $minor, string $cur): string
    {
        $n = number_format(abs($minor) / 100, 2);

        return strtoupper($cur) === 'USD' ? '$'.$n : $n.' '.strtoupper($cur);
    }

    /** @return array<string,mixed>|null */
    public static function find(string $key, ?string $merchantId = null): ?array
    {
        $q = DB::table('invoices')->where(function ($w) use ($key) {
            $w->where('id', $key)->orWhere('invoice_number', $key)->orWhere('transaction_id', $key);
        });
        if ($merchantId) {
            $q->where('merchant_id', $merchantId);
        }
        $inv = $q->first();

        return $inv ? self::build($inv) : null;
    }

    /** @return array<string,mixed> */
    public static function build(object $inv): array
    {
        $m = DB::table('merchants')->where('id', $inv->merchant_id)->first();
        $meta = json_decode($inv->metadata ?? '{}', true) ?: [];
        $cur = trim((string) ($inv->currency ?: 'USD'));

        $customerName = $meta['customer_name'] ?? null;
        if (! $customerName && $inv->transaction_id) {
            $tm = json_decode((string) DB::table('transactions')->where('id', $inv->transaction_id)->value('metadata'), true) ?: [];
            $customerName = $tm['display_name'] ?? null;
        }
        $email = $meta['email'] ?? null;

        $items = [];
        foreach (json_decode($inv->line_items ?? '[]', true) ?: [] as $li) {
            if (isset($li['note']) || str_contains((string) ($li['description'] ?? ''), '(informational)')) {
                continue; // internal platform-fee line, never shown to the customer
            }
            $qty = max(1, (int) ($li['quantity'] ?? 1));
            $items[] = [
                'description' => (string) ($li['description'] ?? 'Subscription'),
                'qty' => $qty,
                'unit' => self::money((int) ($li['unit_amount_minor'] ?? 0), $cur),
                'amount' => self::money((int) ($li['amount_minor'] ?? 0), $cur),
            ];
        }
        if (! $items) {
            $items[] = ['description' => 'Subscription', 'qty' => 1, 'unit' => self::money((int) $inv->subtotal_minor, $cur), 'amount' => self::money((int) $inv->subtotal_minor, $cur)];
        }

        $issued = Carbon::parse($inv->issued_at ?? $inv->created_at);
        $ref = (string) ($meta['provider_reference'] ?? '');
        $paid = $inv->status === 'paid';

        return [
            'id' => $inv->id,
            'number' => $inv->invoice_number,
            'status' => $paid ? 'PAID' : strtoupper((string) $inv->status),
            'paid' => $paid,
            'issued' => $issued->format('d M Y'),
            'due' => $issued->format('d M Y'),
            'currency' => $cur,
            'from' => [
                'name' => (string) ($m->name ?? 'Merchant'),
                'address' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($m->billing_address ?? '')) ?: []))),
                'tax_id' => (string) ($m->tax_id ?? ''),
                'email' => (string) ($m->email ?? ''),
                'logo_url' => $m ? MerchantBranding::logoUrl($m) : null,
                'logo_uri' => $m ? MerchantBranding::logoDataUri($m) : null,
                'initials' => MerchantBranding::initials((string) ($m->name ?? 'M')),
            ],
            'to' => ['name' => $customerName ?: ($email ?: 'Customer'), 'email' => $email],
            'items' => $items,
            'subtotal' => self::money((int) $inv->subtotal_minor, $cur),
            'tax' => self::money((int) $inv->tax_amount_minor, $cur),
            'has_tax' => (int) $inv->tax_amount_minor > 0,
            'total' => self::money((int) $inv->total_minor, $cur),
            'amount_paid' => self::money($paid ? (int) $inv->total_minor : 0, $cur),
            'balance' => self::money($paid ? 0 : (int) $inv->total_minor, $cur),
            'payment_method' => str_starts_with(strtoupper($ref), 'PAYPAL') || $ref === '' ? 'PayPal' : 'Card',
            'payment_ref' => $ref,
            'merchant_id' => $inv->merchant_id,
            'kind' => (string) ($meta['kind'] ?? ''),
            'access_code' => (string) ($meta['access_code'] ?? ''),
            'access_days' => $meta['access_days'] ?? null,
            'product' => (string) ($meta['product'] ?? ''),
        ];
    }

    /** Sample data for the "Preview invoice" button in Settings. @return array<string,mixed> */
    public static function sample(string $merchantId): array
    {
        $m = DB::table('merchants')->where('id', $merchantId)->first();
        $inv = (object) [
            'id' => 'preview', 'merchant_id' => $merchantId, 'transaction_id' => null, 'invoice_number' => 'LUVI-'.now()->format('Y').'-PREVIEW',
            'status' => 'paid', 'currency' => $m->currency ?? 'USD', 'subtotal_minor' => 2000, 'tax_amount_minor' => 0, 'total_minor' => 2000,
            'line_items' => json_encode([['description' => 'Premium Subscription — 30 days', 'quantity' => 1, 'unit_amount_minor' => 2000, 'amount_minor' => 2000]]),
            'metadata' => json_encode(['customer_name' => 'Sarah Johnson', 'email' => 'sarah@example.com', 'provider_reference' => 'PAYPAL-SANDBOX-PREVIEW']),
            'issued_at' => now(), 'created_at' => now(),
        ];

        return self::build($inv);
    }
}
