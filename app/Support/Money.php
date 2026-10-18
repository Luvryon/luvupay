<?php

namespace App\Support;

use InvalidArgumentException;
use Stringable;

/** Immutable minor-unit money (cents). Never use floats for money. */
final class Money implements Stringable
{
    public readonly int $amountMinor;
    public readonly string $currency;

    public function __construct(int $amountMinor, string $currency = 'USD')
    {
        if ($amountMinor < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative.');
        }
        $this->amountMinor = $amountMinor;
        $this->currency = strtoupper($currency);
    }

    public static function of(int $amountMinor, string $currency = 'USD'): self
    {
        return new self($amountMinor, $currency);
    }

    public static function zero(string $currency = 'USD'): self
    {
        return new self(0, $currency);
    }

    public function isZero(): bool
    {
        return $this->amountMinor === 0;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amountMinor + $other->amountMinor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amountMinor - $other->amountMinor, $this->currency);
    }

    /**
     * Split by basis points (10000 = 100%).
     * Remainder goes to the last part to avoid rounding loss.
     *
     * @param  array<string,int>  $bpsParts
     * @return array<string,self>
     */
    /** @param array<string,int> $bpsParts */
    public function allocate(array $bpsParts): array
    {
        return $this->splitByBps($bpsParts);
    }

    public function splitByBps(array $bpsParts): array
    {
        $total = array_sum($bpsParts);
        if ($total !== 10000) {
            throw new InvalidArgumentException("BPS parts must sum to 10000, got {$total}");
        }
        $result = [];
        $allocated = 0;
        $keys = array_keys($bpsParts);
        $lastKey = end($keys);
        foreach ($bpsParts as $key => $bps) {
            if ($key === $lastKey) {
                $result[$key] = new self($this->amountMinor - $allocated, $this->currency);
            } else {
                $part = intdiv($this->amountMinor * $bps, 10000);
                $result[$key] = new self($part, $this->currency);
                $allocated += $part;
            }
        }
        return $result;
    }

    public function format(): string
    {
        return number_format($this->amountMinor / 100, 2) . ' ' . $this->currency;
    }

    public function __toString(): string
    {
        return $this->format();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Currency mismatch: {$this->currency} vs {$other->currency}");
        }
    }
}
