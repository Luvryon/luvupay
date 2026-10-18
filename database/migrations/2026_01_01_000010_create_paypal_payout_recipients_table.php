<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paypal_payout_recipients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('email');
            $table->string('label')->nullable(); // e.g. "Primary charity", "Ops refunds"
            $table->string('purpose', 32)->default('general'); // charity | resale_seller | refund | general
            $table->string('status', 32)->default('pending_verification'); // pending_verification | active | rejected | disabled
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('activates_at')->nullable(); // 24h cooling-off
            $table->timestamp('activated_at')->nullable();
            $table->foreignUuid('requested_by')->nullable(); // merchant_users.id
            $table->string('verification_token', 64)->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'email', 'purpose'], 'payout_recipient_unique');
            $table->index(['merchant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paypal_payout_recipients');
    }
};
