<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per AI Smart-Convert run: the single source of truth for the "AI Negotiation Evidence" page.
 * Everything shown to a judge is read from here (server-side), never from the browser.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('smart_convert_runs')) {
            return;
        }

        Schema::create('smart_convert_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('merchant_id')->index();
            $table->uuid('transaction_id')->nullable()->index();

            // proposed (waiting for merchant) | executed | rejected | failed
            $table->string('status', 24)->default('proposed')->index();
            // auto = AI decides and PayPal executes; propose = merchant must approve first
            $table->string('mode', 16)->default('auto');
            // ai = a live model produced the split; fallback = deterministic split (explicitly labelled, never hidden)
            $table->string('path', 16)->default('ai');

            // Model evidence
            $table->string('model', 160)->nullable();
            $table->string('provider', 160)->nullable();
            $table->unsignedInteger('tokens_used')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->uuid('ai_log_id')->nullable();          // -> ai_request_logs.id
            $table->string('ai_error', 500)->nullable();
            $table->json('rationale')->nullable();          // short steps the model stated in its structured answer
            $table->text('reasoning_trace')->nullable();    // provider-returned reasoning, only when the endpoint sends it
            $table->text('narrative')->nullable();
            $table->unsignedTinyInteger('risk_score')->nullable();

            // Split (minor units). raw_* = exactly what the model returned; final = after guardrails
            $table->json('input_snapshot')->nullable();
            $table->json('raw_split')->nullable();
            $table->json('guardrails')->nullable();         // list of adjustments applied to the raw split
            $table->unsignedBigInteger('unused_value_minor');
            $table->unsignedBigInteger('store_credit_minor');
            $table->unsignedBigInteger('charity_minor');
            $table->unsignedBigInteger('fee_minor');
            $table->string('currency', 3)->default('USD');

            // PayPal Payouts evidence
            $table->boolean('paypal_live')->default(false);       // true only when PayPal itself returned the batch id
            $table->boolean('paypal_simulated')->default(false);
            $table->string('paypal_sender_batch_id', 120)->nullable();   // what you search for in the PayPal dashboard
            $table->string('paypal_batch_id', 120)->nullable();          // payout_batch_id returned by PayPal (null unless real)
            $table->string('paypal_batch_status', 40)->nullable();
            $table->string('paypal_receiver_masked', 190)->nullable();
            $table->string('paypal_mode', 16)->nullable();
            $table->string('paypal_error', 500)->nullable();

            $table->string('decided_by', 190)->nullable();        // merchant user who approved / rejected
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_convert_runs');
    }
};
