<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sandbox/demo events are written on behalf of one merchant. Without this column the console's "webhook logs"
 * showed the newest events of ALL merchants (including real inbound provider payloads). Provider events that cannot
 * be attributed stay NULL and are never shown in a merchant's console.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->string('merchant_id', 36)->nullable()->after('provider');
            $table->index(['merchant_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropIndex(['merchant_id', 'received_at']);
            $table->dropColumn('merchant_id');
        });
    }
};
