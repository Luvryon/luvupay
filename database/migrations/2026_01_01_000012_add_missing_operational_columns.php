<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'plan_code')) {
                $table->string('plan_code')->nullable()->after('provider_plan_id');
            }
            if (! Schema::hasColumn('subscriptions', 'billing_interval')) {
                $table->string('billing_interval', 16)->nullable()->after('currency');
            }
            if (! Schema::hasColumn('subscriptions', 'charity_wallet_id')) {
                $table->foreignUuid('charity_wallet_id')->nullable()->after('merchant_id');
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'resale_listing_id')) {
                $table->uuid('resale_listing_id')->nullable()->index()->after('subscription_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            foreach (['plan_code', 'billing_interval', 'charity_wallet_id'] as $col) {
                if (Schema::hasColumn('subscriptions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'resale_listing_id')) {
                $table->dropColumn('resale_listing_id');
            }
        });
    }
};
