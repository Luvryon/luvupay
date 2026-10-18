<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authoritative PayPal link + onboarding state on the merchant row.
 * Session alone is never enough in production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            if (! Schema::hasColumn('merchants', 'paypal_merchant_id')) {
                $table->string('paypal_merchant_id', 64)->nullable()->unique()->after('is_active');
            }
            if (! Schema::hasColumn('merchants', 'paypal_connected_at')) {
                $table->timestamp('paypal_connected_at')->nullable()->after('paypal_merchant_id');
            }
            if (! Schema::hasColumn('merchants', 'paypal_connect_mode')) {
                // oauth | sandbox_demo
                $table->string('paypal_connect_mode', 32)->nullable()->after('paypal_connected_at');
            }
            if (! Schema::hasColumn('merchants', 'onboarding_completed_at')) {
                $table->timestamp('onboarding_completed_at')->nullable()->after('paypal_connect_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            foreach (['onboarding_completed_at', 'paypal_connect_mode', 'paypal_connected_at', 'paypal_merchant_id'] as $col) {
                if (Schema::hasColumn('merchants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
