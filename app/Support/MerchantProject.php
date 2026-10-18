<?php

namespace App\Support;

/**
 * Project a merchant manages in the console.
 * Empty name until merchant creates one. Feature flags control post-grace options.
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
        $stored = session('merchant_project');
        if (is_array($stored) && trim((string) ($stored['name'] ?? '')) !== '') {
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

        return new self('', 'Premium', 2000, 'USD', 'month', 0, 'sandbox', '', false, false, 2000);
    }

    public function isSet(): bool
    {
        return trim($this->name) !== '';
    }

    /** @param  array<string, mixed>  $data */
    public static function store(array $data): self
    {
        $existing = session('merchant_project');
        if (! is_array($existing)) {
            $existing = [];
        }

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
        session(['merchant_project' => $payload]);

        return self::current();
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

    /** @return array<string, scalar|bool> */
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
            'is_set' => $this->isSet(),
            'marketplace_resale' => $this->marketplaceResale,
            'charity_split' => $this->charitySplit,
            'partial_refund_bps' => $this->partialRefundBps,
            'partial_refund_pct' => (int) round($this->partialRefundBps / 100),
        ];
    }
}
