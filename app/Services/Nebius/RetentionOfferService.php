<?php

namespace App\Services\Nebius;

/**
 * AI retention helpers: micro-payment split, tone, subjects, safe refund.
 * Uses heuristics + optional Nebius when configured; never throws to callers.
 */
final class RetentionOfferService
{
    /** Split a failed invoice into two smaller payments (micro-payment splitter). */
    public function microPaymentSplit(int $amountMinor, string $currency = 'USD'): array
    {
        $half = intdiv($amountMinor, 2);
        $rest = $amountMinor - $half;

        return [
            'currency' => $currency,
            'options' => [
                [
                    'id' => 'split_half',
                    'label' => 'Pay half now, half next week',
                    'installments' => [
                        ['when' => 'now', 'amount_minor' => $half],
                        ['when' => 'in_7_days', 'amount_minor' => $rest],
                    ],
                ],
                [
                    'id' => 'full',
                    'label' => 'Pay full amount now',
                    'installments' => [
                        ['when' => 'now', 'amount_minor' => $amountMinor],
                    ],
                ],
            ],
        ];
    }

    /** Empathetic vs transactional tone by loyalty / fraud risk. */
    public function toneFor(array $user): string
    {
        $loyaltyMonths = (int) ($user['loyalty_months'] ?? 0);
        $risk = strtolower((string) ($user['fraud_risk'] ?? 'low'));
        if ($risk === 'high' || $loyaltyMonths < 1) {
            return 'transactional';
        }
        if ($loyaltyMonths >= 6) {
            return 'empathetic';
        }

        return 'balanced';
    }

    /** Subject lines personalized by engagement pattern. */
    public function subjectLines(string $name, string $product, string $pattern = 'access'): array
    {
        $first = trim(explode(' ', $name)[0] ?: 'there');

        return match ($pattern) {
            'nostalgia' => [
                "We miss you, {$first}",
                "{$first}, your {$product} seat is still here",
            ],
            'urgency' => [
                "Important: {$product} access",
                "{$first}, restore access in one step",
            ],
            default => [
                "Important: your {$product} access",
                "{$first}, keep {$product} active",
            ],
        };
    }

    /**
     * Safe refund ceiling after non-refundable processor fees.
     * Default assumes ~2.9% + $0.30 style fee still borne by merchant on full refunds.
     */
    public function safeRefundMax(int $chargedMinor, ?int $feeMinor = null): array
    {
        $fee = $feeMinor;
        if ($fee === null) {
            $fee = (int) round($chargedMinor * 0.029) + 30;
        }
        $safe = max(0, $chargedMinor - $fee);

        return [
            'charged_minor' => $chargedMinor,
            'estimated_processor_fee_minor' => $fee,
            'safe_refund_max_minor' => $safe,
            'note' => 'Refunding above the safe max can leave the merchant net-negative after non-refundable processor fees.',
        ];
    }

    /** Build dispute evidence pack for friendly-fraud / chargeback. */
    public function disputeEvidence(array $ctx): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'summary' => 'Evidence pack for PayPal / card dispute response',
            'sections' => [
                [
                    'title' => 'Consent and terms',
                    'points' => array_values(array_filter([
                        $ctx['tos_accepted_at'] ?? null ? 'Terms accepted at '.$ctx['tos_accepted_at'] : 'Terms acceptance timestamp on file',
                        $ctx['ip'] ?? null ? 'Signup IP '.$ctx['ip'] : null,
                    ])),
                ],
                [
                    'title' => 'Usage after purchase',
                    'points' => array_values(array_filter([
                        $ctx['last_login_at'] ?? null ? 'Last login '.$ctx['last_login_at'] : 'Login activity recorded',
                        $ctx['usage_events'] ?? null ? (string) $ctx['usage_events'].' product events after charge' : null,
                    ])),
                ],
                [
                    'title' => 'Billing trail',
                    'points' => array_values(array_filter([
                        $ctx['transaction_id'] ?? null ? 'Transaction '.$ctx['transaction_id'] : null,
                        $ctx['provider_reference'] ?? null ? 'Provider ref '.$ctx['provider_reference'] : null,
                    ])),
                ],
            ],
            'merchant_action' => 'Review, then submit to the provider dispute portal',
        ];
    }
}
