<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Project a merchant manages in the console.
 * Persisted in merchants.project_json (primary) + session (cache).
 */
final class MerchantProject
{
    public function __construct(
        public readonly string $name,
        public readonly string $plan,
        public readonly int $priceMinor,
        public readonly string $currency,
        public readonly string $interval,
        public readonly int $activeSubscribers,
        public readonly string $environment = 'sandbox',
        public readonly string $description = '',
        public readonly bool $marketplaceResale = false,
        public readonly bool $charitySplit = false,
        public readonly int $partialRefundBps = 2000,
    ) {}

    public static function current(): self
    {
        $stored = self::loadPayload();
        if (is_array($stored) && trim((string) ($stored['name'] ?? '')) !== '') {
            return self::fromPayload($stored);
        }

        return new self('', 'Premium', 2000, 'USD', 'month', 0, 'sandbox', '', false, false, 2000);
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

        $payload = array_merge($existing, [
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
        ]);

        // Session cache (same browser)
        session(['merchant_project' => $payload]);

        // Durable store on merchant row (survives new sessions / servers)
        $merchantId = self::merchantId();
        if ($merchantId && Schema::hasColumn('merchants', 'project_json')) {
            DB::table('merchants')->where('id', $merchantId)->update([
                'project_json' => json_encode($payload),
                'updated_at' => now(),
            ]);
        }

        return self::fromPayload($payload);
    }

    public function product(): string
    {
        return $this->name === '' ? '' : $this->name.' '.$this->plan;
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
        // Prefer DB (durable)
        $merchantId = self::merchantId();
        if ($merchantId && Schema::hasColumn('merchants', 'project_json')) {
            $raw = DB::table('merchants')->where('id', $merchantId)->value('project_json');
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && trim((string) ($decoded['name'] ?? '')) !== '') {
                    // refresh session cache
                    session(['merchant_project' => $decoded]);

                    return $decoded;
                }
            }
        }

        $stored = session('merchant_project');

        return is_array($stored) ? $stored : null;
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
