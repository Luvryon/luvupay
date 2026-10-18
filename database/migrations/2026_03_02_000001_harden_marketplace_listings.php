<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            // Reservation lifecycle: a reserved listing returns to "active" when reserved_until passes.
            $table->timestamp('reserved_until')->nullable()->after('buyer_email');
            // Secret bound to one reservation; must come back on the PayPal return/cancel URLs.
            $table->string('reservation_token', 64)->nullable()->after('reserved_until');
            $table->string('capture_id', 64)->nullable()->after('paypal_order_id');
            // Seller proves control of the email (signed link) before a listing becomes public; see config luvupay.marketplace.
            $table->timestamp('verified_at')->nullable()->after('status');

            $table->index(['status', 'reserved_until']);
            $table->index('paypal_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            $table->dropIndex(['status', 'reserved_until']);
            $table->dropIndex(['paypal_order_id']);
            $table->dropColumn(['reserved_until', 'reservation_token', 'capture_id', 'verified_at']);
        });
    }
};
