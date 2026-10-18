<?php

namespace App\Services\Fraud;

use App\Exceptions\ResaleException;
use App\Exceptions\SecondChanceException;
use App\Models\UserTrustScore;
use Illuminate\Support\Str;

class TrustScoreService
{
    public function for(string $merchantId, string $merchantUserId): UserTrustScore
    {
        return UserTrustScore::query()->firstOrCreate(
            ['merchant_id' => $merchantId, 'merchant_user_id' => $merchantUserId],
            ['id' => (string) Str::uuid(), 'score' => 100]
        );
    }

    public function recordSecondChance(string $merchantId, string $merchantUserId): UserTrustScore
    {
        $s = $this->for($merchantId, $merchantUserId);
        $s->second_chance_count++;
        $s->forgotten_cancel_count++;
        $s->score = max(0, $s->score - 8);
        $s->resale_eligible = $s->score >= 50;
        $s->second_chance_eligible = $s->score >= 30;
        $s->save();

        return $s;
    }

    public function recordResale(string $merchantId, string $merchantUserId): UserTrustScore
    {
        $s = $this->for($merchantId, $merchantUserId);
        $s->resale_count_30d++;
        if ($s->resale_count_30d > 3) {
            $flags = $s->flags ?? [];
            $flags[] = 'resale_rate_limit';
            $s->flags = array_values(array_unique($flags));
            $s->resale_eligible = false;
        }
        $s->save();

        return $s;
    }

    public function assertSecondChanceAllowed(string $merchantId, string $merchantUserId): void
    {
        $s = $this->for($merchantId, $merchantUserId);
        if (! $s->second_chance_eligible) {
            throw new SecondChanceException('trust_blocked', 'Second Chance disabled for low trust score.', 403);
        }
    }

    public function assertResaleAllowed(string $merchantId, string $merchantUserId): void
    {
        $s = $this->for($merchantId, $merchantUserId);
        if (! $s->resale_eligible) {
            throw new ResaleException('trust_blocked', 'Resale disabled for low trust score or rate limit.', 403);
        }
    }
}
