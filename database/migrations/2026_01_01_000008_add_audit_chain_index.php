<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS audit_logs_merchant_id_id_desc ON audit_logs (merchant_id, id DESC)');
        } elseif (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            // MySQL/MariaDB equivalent (DESC index support varies; plain composite is fine)
            Schema::table('audit_logs', function ($table) {
                $table->index(['merchant_id', 'id'], 'audit_logs_merchant_id_id_index');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS audit_logs_merchant_id_id_desc');
        } elseif (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('audit_logs', function ($table) {
                $table->dropIndex('audit_logs_merchant_id_id_index');
            });
        }
    }
};
