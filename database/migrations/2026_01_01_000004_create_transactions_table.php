<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignUuid('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignUuid('parent_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignUuid('charity_wallet_id')->nullable()->constrained('charity_wallets')->nullOnDelete();
            $table->string('type', 32);
            $table->string('status', 32)->default('pending');
            $table->string('idempotency_key')->unique();
            $table->string('merchant_user_id')->nullable();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('USD');
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable()->index();
            $table->string('provider_event_id')->nullable();
            $table->timestamp('second_chance_expires_at')->nullable()->index();
            $table->timestamp('second_chance_triggered_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('split_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'type', 'status']);
            $table->index(['merchant_id', 'subscription_id']);
            $table->index(['status', 'second_chance_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
