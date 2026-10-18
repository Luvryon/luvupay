<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Held = 'held';                 // charge held in 48h Second Chance window
    case Processing = 'processing';     // split in progress
    case Completed = 'completed';
    case Settled = 'settled';           // window expired, merchant keeps 100%
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /**
     * The one place that defines legal lifecycle moves. Completed / Settled / Cancelled are terminal.
     * Failed -> Completed exists on purpose: a payout webhook can arrive after we already marked it failed.
     *
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Pending => [self::Held, self::Processing, self::Completed, self::Settled, self::Failed, self::Cancelled],
            self::Held => [self::Processing, self::Settled, self::Cancelled],
            self::Processing => [self::Completed, self::Failed],
            self::Failed => [self::Processing, self::Completed, self::Cancelled],
            self::Completed, self::Settled, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedNext(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedNext() === [];
    }
}
