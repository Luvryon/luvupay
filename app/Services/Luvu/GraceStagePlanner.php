<?php

namespace App\Services\Luvu;

use App\Services\Resale\ResaleSettlement;
use App\Support\MerchantProject;
use Illuminate\Support\Carbon;

/**
 * Where the NEXT subscription charge (the renewal) is on its timeline, relative to the expiry T of the current
 * paid period, which options exist at that moment and what each would do to the money. Read-only.
 *
 *   h = hours until T       stage
 *   h > 72                  d7         reminder
 *   72 >= h > 48            d3         reminder
 *   48 >= h > 6             h48        reminder
 *   6  >= h > 0             h6         reminder
 *   0  >= h > -24           lapsed     renewal payment failed, options still locked
 *   -24 >= h > -48          grace_open ALL options open (from +1 day after expiry)
 *   h <= -48                expired    window closed
 */
final class GraceStagePlanner
{
    /** Positions the sandbox can jump to (hours relative to T; positive = before expiry). */
    public const POSITIONS = [168, 72, 48, 6, -25, -49];

    public static function keyForHoursToExpiry(float $h): string
    {
        return match (true) {
            $h > 72 => 'd7',
            $h > 48 => 'd3',
            $h > 6 => 'h48',
            $h > 0 => 'h6',
            $h > -24 => 'lapsed',
            $h > -48 => 'grace_open',
            default => 'expired',
        };
    }

    /** @return list<string> */
    public static function allowedChoices(string $stage): array
    {
        return array_values((array) (config('luvupay.grace_stages.'.$stage.'.choices') ?? []));
    }

    public static function hoursToExpiry(?string $periodEnd, ?Carbon $now = null): ?float
    {
        if (! $periodEnd) {
            return null;
        }
        $now ??= now();

        return (Carbon::parse($periodEnd)->getTimestamp() - $now->getTimestamp()) / 3600;
    }

    /** @return array<string,mixed> */
    public function plan(object $renewal, ?object $sub, MerchantProject $project, ?string $displayName = null): array
    {
        $now = now();
        $meta = json_decode((string) ($renewal->metadata ?? '{}'), true) ?: [];
        $h = self::hoursToExpiry($sub->current_period_end ?? null, $now);
        $key = $h === null ? 'grace_open' : self::keyForHoursToExpiry($h);
        $cfg = (array) config('luvupay.grace_stages.'.$key);
        $held = $renewal->status === 'held';
        $allowed = $held ? (array) ($cfg['choices'] ?? []) : [];
        $amount = (int) $renewal->amount_minor;
        $cur = trim((string) $renewal->currency) ?: 'USD';
        $fmt = fn (int $m) => (strtoupper($cur) === 'USD' ? '$' : '').number_format($m / 100, 2).(strtoupper($cur) === 'USD' ? '' : ' '.$cur);
        $name = $displayName ?: 'there';
        $product = (string) ($meta['product'] ?? $project->product() ?: 'subscription');
        $periodEnd = $sub && $sub->current_period_end ? Carbon::parse($sub->current_period_end) : null;

        $span = function (float $hours): string {
            $hours = max(0, $hours);

            return $hours >= 48 ? (int) round($hours / 24).' days' : max(1, (int) ceil($hours)).' hours';
        };
        $time = match ($key) {
            'lapsed' => $span(($h ?? 0) + 24),
            'grace_open' => $span(($h ?? 0) + 48),
            default => $span((float) ($h ?? 0)),
        };

        $lockedWhy = match (true) {
            $renewal->status === 'pending' => 'Options open 1 day after the subscription expires',
            $key === 'lapsed' => 'Options open 1 day after the subscription expires',
            default => 'Closed at this stage',
        };
        $opt = fn (string $id, string $label, string $desc, array $effect, bool $enabled = true, ?string $why = null) => [
            'id' => $id, 'label' => $label, 'description' => $desc, 'effect' => $effect,
            'available' => in_array($id, $allowed, true) && $enabled,
            'reason' => ! in_array($id, $allowed, true) ? $lockedWhy : ($enabled ? null : $why),
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
                $ask = ResaleSettlement::askingPrice($amount, $periodEnd?->copy()->addDays(max(1, (int) config('luvupay.resale.period_days', 30))), $now);
            } catch (\Throwable $e) {
                $askWhy = $e->getMessage();
            }
        }

        $options = [
            $opt('renew', 'Retry payment', 'Collect the renewal and extend the subscription by one period.', ['wallet_delta_minor' => $amount, 'subscription' => 'active']),
            $opt('split_pay', 'Split pay', 'Down payment now, the rest later; access continues while the plan is current.', ['wallet_delta_minor' => $split['now_minor'] ?? 0, 'later_minor' => $split['later_minor'] ?? 0, 'next_due_at' => $split['next_due_at'] ?? null, 'subscription' => 'active'], $split !== null, $splitWhy),
            $opt('partial_refund', 'Partial refund ('.(int) round($project->partialRefundBps / 100).'%)', 'Subscriber cancels and gets part of the renewal back.', ['wallet_delta_minor' => $amount - $refund, 'refund_minor' => $refund, 'subscription' => 'cancelled']),
            $opt('resale', 'Resell remaining', 'List the next period\'s access; the renewal is released to you when it sells.', ['wallet_delta_minor' => $amount, 'asking_minor' => $ask, 'wallet_when' => 'on_sale', 'subscription' => 'resold'], $ask !== null, $askWhy),
            $opt('donate', 'Donate residual', 'Subscriber cancels; a share of the renewal is donated.', ['wallet_delta_minor' => $amount - $donate, 'donation_minor' => $donate, 'subscription' => 'cancelled'], $project->charitySplit, 'Charity Split is off in Settings'),
            $opt('expire', 'Let it expire', 'Nothing is collected and the subscription ends.', ['wallet_delta_minor' => 0, 'subscription' => 'expired']),
        ];

        $reminder = $meta['reminders'][$key] ?? null;
        $template = strtr((string) ($cfg['message'] ?? ''), ['{name}' => $name, '{amount}' => $fmt($amount), '{time}' => $time, '{product}' => $product, '{date}' => $periodEnd ? $periodEnd->format('d M Y') : 'the renewal date']);

        return [
            'stage' => $key,
            'label' => (string) ($cfg['label'] ?? $key),
            'tone' => (string) ($cfg['tone'] ?? 'calm'),
            'is_reminder' => (bool) ($cfg['reminder'] ?? false),
            'hours_to_expiry' => $h === null ? null : round($h, 1),
            'hours_left' => $h === null ? null : round(max(0, $key === 'grace_open' ? $h + 48 : $h), 1),
            'expires_at' => $periodEnd?->toIso8601String(),
            'deadline_at' => $renewal->second_chance_expires_at ? Carbon::parse($renewal->second_chance_expires_at)->toIso8601String() : null,
            'message' => (string) ($reminder['body'] ?? $template),
            'message_source' => $reminder['source'] ?? 'template',
            'reminder' => $reminder,
            'renewal_status' => $renewal->status,
            'amount_minor' => $amount,
            'currency' => $cur,
            'options' => $options,
            'timeline' => collect(self::POSITIONS)->map(fn (int $p) => ['hours' => $p, 'label' => match ($p) {
                168 => '7 days before', 72 => '3 days before', 48 => '48 hours before', 6 => '6 hours before', -25 => '+1 day after expiry', default => 'Window closed',
            }])->all(),
        ];
    }
}
