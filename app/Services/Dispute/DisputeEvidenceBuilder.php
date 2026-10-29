<?php

namespace App\Services\Dispute;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builds a PayPal-oriented dispute evidence pack from real ledger / subscription rows.
 * Missing fields are marked unavailable (never invents fake IP/login hours).
 */
final class DisputeEvidenceBuilder
{
    /**
     * @return array{ok:bool,summary:string,sections:list<array{title:string,points:list<string>}>,raw:array,transaction_id:?string}
     */
    public function build(string $merchantId, string $transactionId): array
    {
        $tx = DB::table('transactions')
            ->where('merchant_id', $merchantId)
            ->where('id', $transactionId)
            ->first();

        if (! $tx) {
            return [
                'ok' => false,
                'summary' => 'Transaction not found',
                'sections' => [],
                'raw' => [],
                'transaction_id' => $transactionId,
            ];
        }

        $meta = json_decode($tx->metadata ?? '{}', true) ?: [];
        $sub = null;
        if ($tx->subscription_id) {
            $sub = DB::table('subscriptions')->where('id', $tx->subscription_id)->first();
        }

        $email = (string) ($tx->email ?? $sub->email ?? '');
        $name = (string) ($meta['display_name'] ?? (str_contains($email, '@') ? explode('@', $email)[0] : 'Customer'));
        $amount = number_format(((int) $tx->amount_minor) / 100, 2);
        $currency = trim((string) $tx->currency) ?: 'USD';
        $amountLabel = $currency === 'USD' ? '$'.$amount : $amount.' '.$currency;

        $ipSignup = $meta['signup_ip'] ?? $meta['ip'] ?? $meta['client_ip'] ?? null;
        $ipLast = $meta['last_login_ip'] ?? $meta['last_ip'] ?? $ipSignup;
        $tosAt = $meta['tos_accepted_at'] ?? $meta['terms_accepted_at'] ?? null;
        $tosVersion = $meta['tos_version'] ?? $meta['terms_version'] ?? null;
        $lastLogin = $meta['last_login_at'] ?? null;
        $usageHours = $meta['usage_hours'] ?? $meta['streamed_hours'] ?? null;
        $usageEvents = $meta['usage_events'] ?? null;
        $providerRef = $tx->provider_reference ?? null;
        $paypalSub = $sub->provider_subscription_id ?? ($meta['paypal_subscription_id'] ?? null);

        // Related resale / refund activity from ledger
        $related = DB::table('transactions')
            ->where('merchant_id', $merchantId)
            ->where(function ($q) use ($tx) {
                $q->where('parent_transaction_id', $tx->id)
                    ->orWhere('subscription_id', $tx->subscription_id);
            })
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['type', 'status', 'amount_minor', 'created_at', 'provider_reference']);

        $consentPoints = [];
        if ($tosAt) {
            $consentPoints[] = 'Terms accepted at '.$tosAt.($tosVersion ? ' (version '.$tosVersion.')' : '');
        } else {
            $consentPoints[] = 'Terms acceptance timestamp not stored on this charge - attach storefront audit log if available';
        }
        if ($ipSignup) {
            $consentPoints[] = 'Signup / checkout IP: '.$ipSignup;
        } else {
            $consentPoints[] = 'Signup IP not recorded on this transaction metadata';
        }

        $usagePoints = [];
        if ($lastLogin) {
            $usagePoints[] = 'Last login at '.$lastLogin;
        }
        if ($ipLast) {
            $usagePoints[] = 'Last known IP: '.$ipLast;
            if ($ipSignup && $ipLast === $ipSignup) {
                $usagePoints[] = 'Last IP matches signup IP';
            }
        }
        if ($usageHours !== null) {
            $usagePoints[] = 'Recorded product usage: '.$usageHours.' hours';
        }
        if ($usageEvents !== null) {
            $usagePoints[] = 'Product events after charge: '.$usageEvents;
        }
        if ($usagePoints === []) {
            $usagePoints[] = 'No usage telemetry on this row - export app login logs for the customer email '.$email;
        }

        $billingPoints = [
            'Transaction ID: '.$tx->id,
            'Customer: '.$name.($email ? ' <'.$email.'>' : ''),
            'Charge amount: '.$amountLabel,
            'Transaction status: '.$tx->status,
            'Created at: '.$tx->created_at,
        ];
        if ($providerRef) {
            $billingPoints[] = 'Provider reference: '.$providerRef;
        }
        if ($paypalSub) {
            $billingPoints[] = 'PayPal subscription ID: '.$paypalSub;
        }
        foreach ($related as $r) {
            $billingPoints[] = strtoupper((string) $r->type).' / '.$r->status.' / '
                .number_format(((int) $r->amount_minor) / 100, 2)
                .' @ '.$r->created_at
                .($r->provider_reference ? ' ('.$r->provider_reference.')' : '');
        }

        // Optional audit trail table
        if (Schema::hasTable('audit_logs')) {
            $audits = DB::table('audit_logs')
                ->where('merchant_id', $merchantId)
                ->where(function ($q) use ($tx, $email) {
                    $q->where('subject_id', $tx->id);
                    if ($email !== '') {
                        $q->orWhere('metadata', 'like', '%'.$email.'%');
                    }
                })
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['action', 'created_at']);
            foreach ($audits as $a) {
                $billingPoints[] = 'Audit: '.$a->action.' @ '.$a->created_at;
            }
        }

        return [
            'ok' => true,
            'summary' => 'Evidence pack for '.$name.' - '.$amountLabel.' (PayPal Resolution Center)',
            'sections' => [
                ['title' => 'Consent and terms', 'points' => $consentPoints],
                ['title' => 'Usage after purchase', 'points' => $usagePoints],
                ['title' => 'Billing trail', 'points' => $billingPoints],
            ],
            'raw' => [
                'transaction_id' => $tx->id,
                'email' => $email,
                'amount_minor' => (int) $tx->amount_minor,
                'currency' => $currency,
                'metadata_keys' => array_keys($meta),
            ],
            'transaction_id' => $tx->id,
            'customer_name' => $name,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
