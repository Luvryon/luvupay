<?php

namespace Tests\Feature;

use App\Exceptions\LedgerException;
use App\Models\Ledger\LedgerEntry;
use App\Services\Ledger\LedgerPoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_balanced_journal_is_posted_and_trial_balance_holds(): void
    {
        $p = app(LedgerPoster::class);
        $p->post('t:1', 'test', 'USD', [[LedgerPoster::CLEARING, 'debit', 1000], [LedgerPoster::USER_REFUND, 'credit', 600], [LedgerPoster::CHARITY, 'credit', 400]]);

        $this->assertTrue($p->trialBalance()['USD']['balanced']);
        $this->assertSame(1000, $p->balance(LedgerPoster::CLEARING, 'USD'));
        $this->assertSame(-600, $p->balance(LedgerPoster::USER_REFUND, 'USD'));
    }

    public function test_unbalanced_journal_is_refused_and_leaves_no_trace(): void
    {
        $this->expectException(LedgerException::class);
        try {
            app(LedgerPoster::class)->post('t:bad', 'test', 'USD', [[LedgerPoster::CLEARING, 'debit', 1000], [LedgerPoster::CHARITY, 'credit', 999]]);
        } finally {
            $this->assertDatabaseCount('ledger_journals', 0);
        }
    }

    public function test_same_reference_is_posted_exactly_once(): void
    {
        $p = app(LedgerPoster::class);
        $lines = [[LedgerPoster::CLEARING, 'debit', 500], [LedgerPoster::CHARITY, 'credit', 500]];
        $a = $p->post('t:dup', 'test', 'USD', $lines);
        $b = $p->post('t:dup', 'test', 'USD', $lines);

        $this->assertSame($a->id, $b->id);
        $this->assertDatabaseCount('ledger_entries', 2);
    }

    public function test_entries_are_immutable(): void
    {
        app(LedgerPoster::class)->post('t:imm', 'test', 'USD', [[LedgerPoster::CLEARING, 'debit', 5], [LedgerPoster::CHARITY, 'credit', 5]]);
        $entry = LedgerEntry::query()->firstOrFail();

        $this->expectException(LedgerException::class);
        $entry->update(['amount_minor' => 1]);
    }

    public function test_zero_amount_lines_are_dropped(): void
    {
        $j = app(LedgerPoster::class)->post('t:zero', 'test', 'USD', [[LedgerPoster::CLEARING, 'debit', 100], [LedgerPoster::CHARITY, 'credit', 100], [LedgerPoster::FEES, 'credit', 0]]);
        $this->assertSame(2, $j->entries()->count());
    }
}
