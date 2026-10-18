<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->uuid('merchant_id')->nullable()->index();
            $table->string('auditable_type');
            $table->string('auditable_id');
            $table->string('event', 48);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('actor_type', 32)->nullable();
            $table->string('actor_id')->nullable();
            $table->uuid('request_id')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at');
            $table->string('prev_hash', 64)->nullable();
            $table->string('hash', 64);

            $table->index(['merchant_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['event']);
        });

        // Append-only triggers only on PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                CREATE OR REPLACE FUNCTION prevent_audit_log_mutation()
                RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION \'audit_logs is append-only\';
                END;
                $$ LANGUAGE plpgsql;
            ');
            DB::statement('
                CREATE TRIGGER audit_logs_no_update
                BEFORE UPDATE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION prevent_audit_log_mutation();
            ');
            DB::statement('
                CREATE TRIGGER audit_logs_no_delete
                BEFORE DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION prevent_audit_log_mutation();
            ');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS audit_logs_no_update ON audit_logs');
            DB::statement('DROP TRIGGER IF EXISTS audit_logs_no_delete ON audit_logs');
            DB::statement('DROP FUNCTION IF EXISTS prevent_audit_log_mutation()');
        }
        Schema::dropIfExists('audit_logs');
    }
};
