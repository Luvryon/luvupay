<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\SecondChanceException;
use App\Models\Merchant;
use App\Models\Transaction;
use App\Models\UserTrustScore;
use App\Services\Fraud\TrustScoreService;
use App\Services\Luvu\SecondChanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecondChanceTrustTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_trust_blocks_second_chance(): void
    {
        $m = Merchant::create([
            'id' => (string) Str::uuid(),
            'name' => 'M',
            'email' => 'm@test.com',
            'api_key_hash' => hash('sha256', 'k'),
            'is_active' => true,
        ]);
        UserTrustScore::create([
            'id' => (string) Str::uuid(),
            'merchant_id' => $m->id,
            'merchant_user_id' => 'u1',
            'score' => 10,
            'second_chance_eligible' => false,
            'resale_eligible' => false,
        ]);

        $tx = Transaction::create([
            'id' => (string) Str::uuid(),
            'merchant_id' => $m->id,
            'type' => TransactionType::Charge,
            'status' => TransactionStatus::Held,
            'idempotency_key' => 't1',
            'merchant_user_id' => 'u1',
            'email' => 'u1@test.com',
            'amount_minor' => 2000,
            'currency' => 'USD',
            'second_chance_expires_at' => now()->addDay(),
        ]);

        $this->expectException(SecondChanceException::class);
        app(SecondChanceService::class)->trigger($tx, 'test');
    }
}
