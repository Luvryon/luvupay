<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-merchant project. Empty until Create project.
 * Source of truth: merchants.project_json (then session cache).
 */
final class MerchantProject
{
    public function __construct(
        public readonly string $name = '',
        public readonly string $plan = 'Premium',
        public readonly int $priceMinor = 2000,
        public readonly string $currency = 'USD',
        public readonly string $interval = 'month',
        public readonly int $activeSubscribers = 0,
        public readonly string $environment = 'sandbox',
        public readonly string $description = '',
        public readonly bool $marketplaceResale = false,
        public readonly bool $charitySplit = false,
        public readonly int $partialRefundBps = 2000,
    ) {}

    public static function current(): self
    {
        $payload = self::loadPayload();
        if ($payload && trim((string) ($payload['name'] ?? '')) !== '') {
            return self::fromPayload($payload);
        }

        return new self;
    }

    public function isSet(): bool
    {
        return trim($this->name) !== '';
    }

    /** @param  array<string, mixed>  $data */
    public static function store(array $data): self
    {
        $existing = self::loadPayload() ?: [];
        $name = trim((string) ($data['name'] ?? $existing['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Project name is required.');
        }

        $payload = [
            'name' => $name,
            'plan' => (string) ($data['plan'] ?? $existing['plan'] ?? 'Premium'),
            'price_minor' => max(100, (int) ($data['price_minor'] ?? $existing['price_minor'] ?? 2000)),
            'currency' => strtoupper((string) ($data['currency'] ?? $existing['currency'] ?? 'USD')),
            'interval' => (string) ($data['interval'] ?? $existing['interval'] ?? 'month'),
            'active_subscribers' => (int) ($existing['active_subscribers'] ?? 0),
            'environment' => (($data['environment'] ?? $existing['environment'] ?? 'sandbox') === 'production') ? 'production' : 'sandbox',
            'description' => (string) ($data['description'] ?? $existing['description'] ?? ''),
            'marketplace_resale' => (bool) ($data['marketplace_resale'] ?? $existing['marketplace_resale'] ?? false),
            'charity_split' => (bool) ($data['charity_split'] ?? $existing['charity_split'] ?? false),
            'partial_refund_bps' => max(0, min(10000, (int) ($data['partial_refund_bps'] ?? $existing['partial_refund_bps'] ?? 2000))),
        ];

        session([self::sessionKey() => $payload]);

        $merchantId = self::merchantId();
        if ($merchantId) {
            try {
                if (Schema::hasColumn('merchants', 'project_json')) {
                    DB::table('merchants')->where('id', $merchantId)->update([
                        'project_json' => json_encode($payload),
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return self::fromPayload($payload);
    }

    public function product(): string
    {
        return $this->name === '' ? '' : trim($this->name.' '.$this->plan);
    }

    public function price(): string
    {
        return $this->currency === 'USD'
            ? '$'.number_format($this->priceMinor / 100, 2)
            : number_format($this->priceMinor / 100, 2).' '.$this->currency;
    }

    public function priceCompact(): string
    {
        return $this->priceMinor % 100 === 0
            ? ($this->currency === 'USD' ? '$' : '').intdiv($this->priceMinor, 100).($this->currency === 'USD' ? '' : ' '.$this->currency)
            : $this->price();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'plan' => $this->plan,
            'product' => $this->product(),
            'price_minor' => $this->priceMinor,
            'price' => $this->price(),
            'currency' => $this->currency,
            'interval' => $this->interval,
            'active_subscribers' => $this->activeSubscribers,
            'environment' => $this->environment,
            'description' => $this->description,
            'marketplace_resale' => $this->marketplaceResale,
            'charity_split' => $this->charitySplit,
            'partial_refund_bps' => $this->partialRefundBps,
            'is_set' => $this->isSet(),
        ];
    }

    /** @param  array<string, mixed>  $stored */
    private static function fromPayload(array $stored): self
    {
        return new self(
            (string) $stored['name'],
            (string) ($stored['plan'] ?? 'Premium'),
            max(100, (int) ($stored['price_minor'] ?? 2000)),
            strtoupper((string) ($stored['currency'] ?? 'USD')),
            (string) ($stored['interval'] ?? 'month'),
            max(0, (int) ($stored['active_subscribers'] ?? 0)),
            (($stored['environment'] ?? 'sandbox') === 'production') ? 'production' : 'sandbox',
            (string) ($stored['description'] ?? ''),
            (bool) ($stored['marketplace_resale'] ?? false),
            (bool) ($stored['charity_split'] ?? false),
            max(0, min(10000, (int) ($stored['partial_refund_bps'] ?? 2000))),
        );
    }

    /** @return array<string, mixed>|null */
    private static function loadPayload(): ?array
    {
        $merchantId = self::merchantId();
        if ($merchantId) {
            try {
                if (Schema::hasColumn('merchants', 'project_json')) {
                    $raw = DB::table('merchants')->where('id', $merchantId)->value('project_json');
                    if (is_string($raw) && $raw !== '') {
                        $decoded = json_decode($raw, true);
                        if (is_array($decoded) && trim((string) ($decoded['name'] ?? '')) !== '') {
                            session([self::sessionKey() => $decoded]);

                            return $decoded;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // column missing before migrate — ignore
            }
        }

        $stored = session(self::sessionKey());

        return is_array($stored) && trim((string) ($stored['name'] ?? '')) !== '' ? $stored : null;
    }

    private static function sessionKey(): string
    {
        $id = self::merchantId();

        return $id ? 'merchant_project_'.$id : 'merchant_project_anon';
    }

    private static function merchantId(): ?string
    {
        try {
            $user = Auth::guard('merchant_web')->user();
            if ($user && ! empty($user->merchant_id)) {
                return (string) $user->merchant_id;
            }
        } catch (\Throwable $e) {
            //
        }

        return null;
    }
}
