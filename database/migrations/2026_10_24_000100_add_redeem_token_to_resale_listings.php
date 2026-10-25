<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('resale_listings') && ! Schema::hasColumn('resale_listings', 'redeem_token')) {
            Schema::table('resale_listings', function (Blueprint $table) {
                $table->string('redeem_token', 80)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('resale_listings', 'redeem_token')) {
            Schema::table('resale_listings', function (Blueprint $table) {
                $table->dropColumn('redeem_token');
            });
        }
    }
};
