<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Split pay: a charge the subscriber settles in instalments (down payment now, the rest later).
 * One plan per charge. `installments` is the schedule; the money itself lives in `transactions` rows of type `installment`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->uuid('subscription_id')->nullable()->index();
            $table->uuid('charge_id')->unique();               // the charge being split (one plan per charge)
            $table->string('email')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->unsignedBigInteger('total_minor');
            $table->unsignedBigInteger('paid_minor')->default(0);
            $table->json('installments');                       // [{n, amount_minor, due_at, status, paid_at, transaction_id}]
            $table->string('status', 16)->default('active');    // active | completed | defaulted | cancelled
            $table->timestamp('next_due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('defaulted_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
            $table->index(['status', 'next_due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_plans');
    }
};
