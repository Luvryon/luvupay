<?php

namespace App\Services\Nebius;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AiResponseCache
{
    public function get(string $feature, string $inputHash): ?array
    {
        $store = config('nebius.cache_store');
        $key = "nebius:{$feature}:{$inputHash}";
        try {
            $cached = $store ? Cache::store($store)->get($key) : Cache::get($key);
            if (is_array($cached)) {
                return $cached;
            }
        } catch (Throwable) {
            // fall through to DB
        }

        try {
            $row = DB::table('ai_cached_responses')
                ->where('feature_type', $feature)
                ->where('input_hash', $inputHash)
                ->where('expires_at', '>', now())
                ->first();
            if ($row) {
                return json_decode($row->response_payload, true) ?: null;
            }
        } catch (Throwable) {
        }

        return null;
    }

    public function put(string $feature, string $inputHash, array $payload): void
    {
        $ttl = (int) config('nebius.cache_ttl_seconds', 86400);
        $store = config('nebius.cache_store');
        $key = "nebius:{$feature}:{$inputHash}";
        try {
            if ($store) {
                Cache::store($store)->put($key, $payload, $ttl);
            } else {
                Cache::put($key, $payload, $ttl);
            }
        } catch (Throwable) {
        }

        try {
            DB::table('ai_cached_responses')->updateOrInsert(
                ['feature_type' => $feature, 'input_hash' => $inputHash],
                [
                    'id' => (string) Str::uuid(),
                    'response_payload' => json_encode($payload),
                    'expires_at' => now()->addSeconds($ttl),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        } catch (Throwable) {
        }
    }

    public static function hash(array $input): string
    {
        return hash('sha256', json_encode($input));
    }
}
