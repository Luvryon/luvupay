<?php

namespace App\Services\Usage;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Per-merchant monthly API call counter.
 * Uses Cache (Redis in production, file/array locally).
 */
final class ApiUsageMeter
{
    public function key(string $merchantId): string
    {
        return 'api_usage:'.$merchantId.':'.now()->format('Y-m');
    }

    public function limit(string $merchantId): int
    {
        $limit = DB::table('merchants')->where('id', $merchantId)->value('api_monthly_limit');

        return (int) ($limit ?: config('luvupay.security.default_api_monthly_limit', 1000));
    }

    public function count(string $merchantId): int
    {
        return (int) Cache::get($this->key($merchantId), 0);
    }

    public function remaining(string $merchantId): int
    {
        return max(0, $this->limit($merchantId) - $this->count($merchantId));
    }

    public function increment(string $merchantId, int $by = 1): int
    {
        $key = $this->key($merchantId);
        if (! Cache::has($key)) {
            Cache::put($key, 0, now()->endOfMonth());
        }

        return (int) Cache::increment($key, $by);
    }

    public function maxOut(string $merchantId): int
    {
        $limit = $this->limit($merchantId);
        Cache::put($this->key($merchantId), $limit, now()->endOfMonth());

        return $limit;
    }

    public function reset(string $merchantId): void
    {
        Cache::forget($this->key($merchantId));
    }

    public function exceeded(string $merchantId): bool
    {
        return $this->count($merchantId) >= $this->limit($merchantId);
    }

    /** @return array{used:int,limit:int,remaining:int,period:string} */
    public function snapshot(string $merchantId): array
    {
        $limit = $this->limit($merchantId);
        $used = $this->count($merchantId);

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => max(0, $limit - $used),
            'period' => now()->format('Y-m'),
        ];
    }
}
