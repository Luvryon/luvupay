<?php

namespace App\Services\PayPal;

use App\Support\Money;

/** Converts between PayPal amount objects and Money. Handles v1 {total,currency} and v2 {value,currency_code}. */
final class PayPalAmount
{
    public static function toMoney(?array $a): ?Money
    {
        if (! $a) {
            return null;
        }
        $value = $a['value'] ?? $a['total'] ?? null;
        $currency = $a['currency_code'] ?? $a['currency'] ?? null;

        return ($value === null || $currency === null)
            ? null
            : Money::of((int) round(((float) $value) * 100), $currency);
    }

    public static function fromMoney(Money $m): array
    {
        return ['currency_code' => $m->currency, 'value' => $m->toDecimalString()];
    }
}
