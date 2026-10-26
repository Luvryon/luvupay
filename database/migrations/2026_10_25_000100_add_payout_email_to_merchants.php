<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('merchants') && ! Schema::hasColumn('merchants', 'payout_email')) {
            Schema::table('merchants', function (Blueprint $table) {
                $table->string('payout_email', 190)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('merchants', 'payout_email')) {
            Schema::table('merchants', function (Blueprint $table) {
                $table->dropColumn('payout_email');
            });
        }
    }
};
