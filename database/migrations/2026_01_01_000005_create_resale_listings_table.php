<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resale_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignUuid('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->string('seller_merchant_user_id');
            $table->string('seller_email');
            $table->string('buyer_merchant_user_id')->nullable();
            $table->string('buyer_email')->nullable();
            $table->string('status', 32)->default('active');
            $table->string('entitlement_status', 32)->default('pending');
            $table->unsignedBigInteger('asking_price_minor');
            $table->string('currency', 3)->default('USD');
            $table->timestamp('access_expires_at');
            $table->timestamp('listed_at')->useCurrent();
            $table->timestamp('reserved_until')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamp('entitlement_sent_at')->nullable();
            $table->timestamp('entitlement_acknowledged_at')->nullable();
            $table->uuid('correlation_id')->nullable()->unique();
            $table->string('buyer_payment_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
            $table->index(['status', 'access_expires_at']);
            $table->index(['subscription_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resale_listings');
    }
};
