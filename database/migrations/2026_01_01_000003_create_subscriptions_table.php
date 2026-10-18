<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('merchant_user_id');                 // merchant's own user id (no native LuvuPay auth)
            $table->string('email');
            $table->string('provider')->default('paypal');     // paypal | mock
            $table->string('provider_subscription_id')->nullable()->index();
            $table->string('provider_plan_id')->nullable();
            $table->string('status', 32)->default('pending_approval'); // SubscriptionStatus
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('USD');
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('next_billing_at')->nullable()->index();
            $table->timestamp('nudge_sent_at')->nullable();
            $table->timestamp('paused_by_nudge_at')->nullable();
            $table->timestamp('nudge_confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedInteger('forgotten_cancel_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'merchant_user_id', 'provider_subscription_id'], 'subs_merchant_user_provider_unique');
            $table->index(['merchant_id', 'merchant_user_id']);
            $table->index(['merchant_id', 'status']);
            $table->index(['merchant_id', 'next_billing_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
