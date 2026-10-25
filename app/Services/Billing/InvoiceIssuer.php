<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class InvoiceIssuer
{
    /**
     * Issue a Stripe-style invoice for a successful charge transaction.
     *
     * @return array{id:string,invoice_number:string}
     */
    public function issueForTransaction(string $merchantId, object $tx, array $extra = []): array
    {
        $merchant = DB::table('merchants')->where('id', $merchantId)->first();
        $seq = (int) DB::table('invoices')->where('merchant_id', $merchantId)->count() + 1;
        $number = sprintf('LUVI-%s-%03d', now()->format('Y'), $seq);

        $subtotal = (int) $tx->amount_minor;
        $platformFee = (int) round($subtotal * 0.029); // illustrative platform fee line
        $tax = (int) ($extra['tax_amount_minor'] ?? 0);
        $total = $subtotal + $tax; // fee is informational / absorbed in product pricing for display

        $lineItems = $extra['line_items'] ?? [
            [
                'description' => ($extra['product'] ?? \App\Support\MerchantProject::current()->product() ?: 'Pro Subscription').' — 30 days',
                'quantity' => 1,
                'unit_amount_minor' => $subtotal,
                'amount_minor' => $subtotal,
            ],
            [
                'description' => 'LuvuPay Platform Fee (informational)',
                'quantity' => 1,
                'unit_amount_minor' => $platformFee,
                'amount_minor' => $platformFee,
                'note' => 'Included in processing — not double-charged',
            ],
        ];

        $id = (string) Str::uuid();
        $now = now();

        DB::table('invoices')->insert([
            'id' => $id,
            'merchant_id' => $merchantId,
            'transaction_id' => $tx->id,
            'invoice_number' => $number,
            'billing_name' => $extra['customer_name'] ?? ($tx->email ?? 'Customer'),
            'billing_address' => $merchant->billing_address ?? "{$merchant->name}\nBilling Department\nUnited States",
            'tax_id' => $merchant->tax_id ?? null,
            'subtotal_minor' => $subtotal,
            'tax_amount_minor' => $tax,
            'total_minor' => $total,
            'currency' => $tx->currency ?? 'USD',
            'status' => 'paid',
            'pdf_url' => null,
            'line_items' => json_encode($lineItems),
            'metadata' => json_encode([
                'provider_reference' => $tx->provider_reference ?? null,
                'email' => $tx->email ?? null,
                'customer_name' => $extra['customer_name'] ?? null,
            ]),
            'issued_at' => $now,
            'paid_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['id' => $id, 'invoice_number' => $number];
    }
}
