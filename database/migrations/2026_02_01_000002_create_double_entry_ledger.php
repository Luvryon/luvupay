<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only double-entry ledger. Every journal must balance (sum debit = sum credit); rows are immutable.
 * On PostgreSQL immutability is also enforced by a trigger, on every driver by the Eloquent models.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 160);
            $table->string('type', 16);                    // asset | liability | revenue | expense
            $table->string('currency', 3);
            $table->uuid('merchant_id')->nullable()->index();
            $table->timestamps();
            $table->unique(['code', 'currency']);
        });

        Schema::create('ledger_journals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference', 191)->unique();    // idempotency: "charge:<tx>", "payout:<tx>", ...
            $table->string('kind', 48)->index();
            $table->uuid('merchant_id')->nullable()->index();
            $table->uuid('transaction_id')->nullable()->index();
            $table->string('currency', 3);
            $table->json('metadata')->nullable();
            $table->timestamp('posted_at');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('journal_id')->constrained('ledger_journals')->restrictOnDelete();
            $table->foreignUuid('account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->string('direction', 6);                // debit | credit
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->timestamp('created_at')->nullable();
            $table->index(['account_id', 'direction']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_amount_positive CHECK (amount_minor > 0)");
            DB::unprepared("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_direction_valid CHECK (direction IN ('debit','credit'))");
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION luvupay_ledger_immutable() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'ledger rows are append-only (% on %)', TG_OP, TG_TABLE_NAME;
END;
$$ LANGUAGE plpgsql;
SQL);
            foreach (['ledger_entries', 'ledger_journals'] as $t) {
                DB::unprepared("CREATE TRIGGER {$t}_immutable BEFORE UPDATE OR DELETE ON {$t} FOR EACH ROW EXECUTE FUNCTION luvupay_ledger_immutable()");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS ledger_entries_immutable ON ledger_entries');
            DB::unprepared('DROP TRIGGER IF EXISTS ledger_journals_immutable ON ledger_journals');
            DB::unprepared('DROP FUNCTION IF EXISTS luvupay_ledger_immutable()');
        }
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_journals');
        Schema::dropIfExists('ledger_accounts');
    }
};
