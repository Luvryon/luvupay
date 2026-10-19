<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('merchant_users')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE merchant_users ALTER COLUMN email_verification_code TYPE VARCHAR(255)');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE merchant_users MODIFY email_verification_code VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('merchant_users')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE merchant_users ALTER COLUMN email_verification_code TYPE VARCHAR(12)');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE merchant_users MODIFY email_verification_code VARCHAR(12) NULL');
        }
    }
};