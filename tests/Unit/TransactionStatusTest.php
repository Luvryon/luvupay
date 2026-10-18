<?php

namespace Tests\Unit;

use App\Enums\TransactionStatus as S;
use PHPUnit\Framework\TestCase;

class TransactionStatusTest extends TestCase
{
    public function test_terminal_states_allow_nothing(): void
    {
        foreach ([S::Completed, S::Settled, S::Cancelled] as $s) {
            $this->assertTrue($s->isTerminal());
            foreach (S::cases() as $to) {
                $this->assertFalse($s->canTransitionTo($to), "{$s->value} -> {$to->value} must be illegal");
            }
        }
    }

    public function test_completed_can_never_become_failed(): void
    {
        $this->assertFalse(S::Completed->canTransitionTo(S::Failed));
    }

    public function test_late_success_after_failure_is_allowed(): void
    {
        $this->assertTrue(S::Failed->canTransitionTo(S::Completed));   // payout webhook arriving after a timeout
    }

    public function test_second_chance_lifecycle(): void
    {
        $this->assertTrue(S::Held->canTransitionTo(S::Processing));
        $this->assertTrue(S::Held->canTransitionTo(S::Settled));
        $this->assertTrue(S::Processing->canTransitionTo(S::Completed));
        $this->assertFalse(S::Held->canTransitionTo(S::Completed));   // must go through Processing
    }
}
