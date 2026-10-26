<?php

namespace App\Support;

/**
 * PayPal charges the SENDER of a Payout. LuvuPay passes that cost to the merchant: the fee is taken out of the
 * merchant's own wallet, never from the platform's PayPal app balance.
 *
 *   wallet $100.00, fee 2%  ->  wallet is debited $100.00, PayPal pays out $98.00 to the merchant, fee $2.00.
 */
final class PayoutFee
{
    /** Smallest amount the merchant must actually receive (net) for a payout to be worth sending. */
    public const MIN_NET_MINOR = 100;

    /** @return array{bps:int,fixed_minor:int,cap_minor:int,min_net_minor:int} */
    public static function config(): array
    {
        return [
            'bps' => max(0, (int) config('luvupay.payout_fee.bps', 200)),
            'fixed_minor' => max(0, (int) config('luvupay.payout_fee.fixed_minor', 0)),
            'cap_minor' => max(0, (int) config('luvupay.payout_fee.cap_minor', 0)), // 0 = no cap
            'min_net_minor' => self::MIN_NET_MINOR,
        ];
    }

    public static function fee(int $grossMinor): int
    {
        $c = self::config();
        $fee = (int) round($grossMinor * $c['bps'] / 10000) + $c['fixed_minor'];
        if ($c['cap_minor'] > 0) {
            $fee = min($fee, $c['cap_minor']);
        }

        return max(0, min($fee, $grossMinor));
    }

    /** @return array{gross_minor:int,fee_minor:int,net_minor:int} */
    public static function quote(int $grossMinor): array
    {
        $fee = self::fee($grossMinor);

        return ['gross_minor' => $grossMinor, 'fee_minor' => $fee, 'net_minor' => $grossMinor - $fee];
    }
}
