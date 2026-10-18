<?php

namespace Tests\Feature;

use App\Services\Ledger\LedgerPoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_ledger_reconciles_ok_and_records_a_run(): void
    {
        app(LedgerPoster::class)->post('t:1', 'test', 'USD', [[LedgerPoster::CLEARING, 'debit', 10], [LedgerPoster::CHARITY, 'credit', 10]]);

        $this->artisan('luvi:reconcile')->assertSuccessful();
        $this->assertDatabaseHas('reconciliation_runs', ['status' => 'ok']);
        $this->assertDatabaseCount('reconciliation_exceptions', 0);
    }

    public function test_corrupted_ledger_is_reported_as_an_open_exception_with_failure_exit_code(): void
    {
        app(LedgerPoster::class)->post('t:1', 'test', 'USD', [[LedgerPoster::CLEARING, 'debit', 10], [LedgerPoster::CHARITY, 'credit', 10]]);
        \DB::table('ledger_entries')->where('direction', 'credit')->update(['amount_minor' => 9]);   // simulate tampering (bypasses the model)

        $this->artisan('luvi:reconcile')->assertFailed();
        $this->assertDatabaseHas('reconciliation_exceptions', ['issue' => 'ledger_unbalanced', 'status' => 'open']);
    }
}
