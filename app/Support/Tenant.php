<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Single place for multi-tenant ownership checks (never trust client-supplied merchant_id). */
final class Tenant
{
    public static function id(Request $request): string
    {
        $id = $request->attributes->get('merchant_id');
        if (! $id && $request->user('merchant_web')) {
            $id = $request->user('merchant_web')->merchant_id;
        }
        if (! $id) {
            throw new HttpException(401, 'No merchant context.');
        }

        return (string) $id;
    }

    /** @return object */
    public static function transactionOrFail(string $merchantId, string $transactionId): object
    {
        $row = DB::table('transactions')
            ->where('merchant_id', $merchantId)
            ->where('id', $transactionId)
            ->first();
        if (! $row) {
            throw new HttpException(404, 'Transaction not found.');
        }

        return $row;
    }

    public static function ownsTransaction(string $merchantId, string $transactionId): bool
    {
        return DB::table('transactions')
            ->where('merchant_id', $merchantId)
            ->where('id', $transactionId)
            ->exists();
    }
}
