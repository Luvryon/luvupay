<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            if (! Schema::hasColumn('merchants', 'currency')) {
                $table->string('currency', 3)->default('USD')->after('name');
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'trust_score')) {
                $table->unsignedTinyInteger('trust_score')->nullable()->after('metadata');
            }
            if (! Schema::hasColumn('transactions', 'churn_risk')) {
                $table->string('churn_risk', 16)->nullable()->after('trust_score'); // low|medium|high
            }
        });

        if (! Schema::hasTable('charity_receipts')) {
            Schema::create('charity_receipts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
                $table->foreignUuid('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
                $table->string('certificate_code', 32)->unique();
                $table->string('charity_name');
                $table->unsignedBigInteger('amount_minor');
                $table->string('currency', 3)->default('USD');
                $table->string('paypal_payout_id')->nullable();
                $table->string('donor_email')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['merchant_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('charity_receipts');
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'churn_risk')) {
                $table->dropColumn('churn_risk');
            }
            if (Schema::hasColumn('transactions', 'trust_score')) {
                $table->dropColumn('trust_score');
            }
        });
        Schema::table('merchants', function (Blueprint $table) {
            if (Schema::hasColumn('merchants', 'currency')) {
                $table->dropColumn('currency');
            }
        });
    }
};
