<?php

namespace App\Services\Luvu;

use App\Services\Resale\ResaleSettlement;
use App\Support\MerchantProject;
use Illuminate\Support\Carbon;

/**
 * Where a held charge is on its missed-cancellation timeline, which options exist at that stage and what each one
 * would do to the money. Read-only: it never writes. The same stage rules are enforced by GraceDecisionService.
 */
final class GraceStagePlanner
{
    public const SELECTABLE_HOURS = [168, 72, 48, 6];

    public static function keyForRemaining(float $hours): string
    {
        return match (true) {
            $hours <= 0 => 'expired',
            $hours <= 6 => 'h6',
            $hours <= 48 => 'h48',
            $hours <= 72 => 'd3',
            default => 'd7',
        };
    }

    /** @return list<string> */
    public static function allowedChoices(string $stage): array
    {
        return array_values((array) (config('luvupay.grace_stages.'.$stage.'.choices') ?? []));
    }

    public static function remainingHours(?string $expiresAt, ?Carbon $now = null): ?float
    {
        if (! $expiresAt) {
            return null;
        }
        $now ??= now();

        return ($now->getTimestamp() - Carbon::parse($expiresAt)->getTimestamp()) / -3600;
    }

    /** @return array<string,mixed> */
    public function plan(object $tx, ?object $sub, MerchantProject $project, ?string $displayName = null): array
    {
        $now = now();
        $hours = self::remainingHours($tx->second_chance_expires_at ?? null, $now);
        $key = $hours === null ? 'h48' : self::keyForRemaining($hours);
        $cfg = (array) config('luvupay.grace_stages.'.$key);
        $allowed = (array) ($cfg['choices'] ?? []);
        $amount = (int) $tx->amount_minor;
        $cur = trim((string) $tx->currency) ?: 'USD';
        $fmt = fn (int $m) => (strtoupper($cur) === 'USD' ? '$' : '').number_format($m / 100, 2).(strtoupper($cur) === 'USD' ? '' : ' '.$cur);
        $left = $hours === null ? '48 hours' : ($hours >= 48 ? (int) round($hours / 24).' days' : max(1, (int) ceil($hours)).' hours');
        $name = $displayName ?: 'there';

        $opt = fn (string $id, string $label, string $desc, array $effect, bool $enabled = true, ?string $why = null) => [
            'id' => $id, 'label' => $label, 'description' => $desc, 'effect' => $effect,
            'available' => in_array($id, $allowed, true) && $enabled,
            'reason' => ! in_array($id, $allowed, true) ? 'Closed at this stage' : ($enabled ? null : $why),
        ];

        $refund = min($amount, (int) round($amount * $project->partialRefundBps / 10000));
        $donate = max(1, min($amount, (int) round($amount * $project->charityBps / 10000)));

        $split = null;
        $splitWhy = null;
        try {
            $q = app(SplitPayService::class)->quote($amount);
            $split = ['now_minor' => $q['amounts'][0], 'later_minor' => $amount - $q['amounts'][0], 'parts' => $q['parts'], 'next_due_at' => $q['due'][1]];
        } catch (\Throwable $e) {
            $splitWhy = $e->getMessage();
        }

        $ask = null;
        $askWhy = null;
        if (! $project->marketplaceResale) {
            $askWhy = 'Marketplace Resale is off in Settings';
        } else {
            try {
                $end = $sub && $sub->current_period_end ? Carbon::parse($sub->current_period_end) : null;
                $ask = ResaleSettlement::askingPrice($amount, $end, $now);
            } catch (\Throwable $e) {
                $askWhy = $e->getMessage();
            }
        }

        $options = [
            $opt('renew', 'Pay in full', 'Collect the outstanding charge and keep the subscription active.', ['wallet_delta_minor' => $amount, 'subscription' => 'active']),
            $opt('split_pay', 'Split pay', 'Down payment now, the rest later; access stays active while the plan is current.', ['wallet_delta_minor' => $split['now_minor'] ?? 0, 'later_minor' => $split['later_minor'] ?? 0, 'next_due_at' => $split['next_due_at'] ?? null, 'subscription' => 'active'], $split !== null, $splitWhy),
            $opt('partial_refund', 'Partial refund', 'Subscriber cancels and gets part of the charge back.', ['wallet_delta_minor' => $amount - $refund, 'refund_minor' => $refund, 'subscription' => 'cancelled']),
            $opt('donate', 'Donate to charity', 'Subscriber cancels; a share of the charge is donated.', ['wallet_delta_minor' => $amount - $donate, 'donation_minor' => $donate, 'subscription' => 'cancelled'], $project->charitySplit, 'Charity Split is off in Settings'),
            $opt('resale', 'Resell on marketplace', 'List the unused access; the charge is released to you when it sells.', ['wallet_delta_minor' => $amount, 'asking_minor' => $ask, 'wallet_when' => 'on_sale', 'subscription' => 'resold'], $ask !== null, $askWhy),
            $opt('expire', 'Let it expire', 'Nothing is collected and the subscription ends.', ['wallet_delta_minor' => 0, 'subscription' => 'expired']),
        ];

        return [
            'stage' => $key,
            'label' => (string) ($cfg['label'] ?? $key),
            'tone' => (string) ($cfg['tone'] ?? 'calm'),
            'hours_left' => $hours === null ? null : round(max(0, $hours), 1),
            'expires_at' => $tx->second_chance_expires_at ? Carbon::parse($tx->second_chance_expires_at)->toIso8601String() : null,
            'message' => strtr((string) ($cfg['message'] ?? ''), ['{name}' => $name, '{amount}' => $fmt($amount), '{time}' => $left]),
            'amount_minor' => $amount,
            'currency' => $cur,
            'options' => $options,
        ];
    }
}
