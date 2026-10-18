<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            if (! Schema::hasColumn('merchants', 'subscription_tier')) {
                $table->string('subscription_tier', 32)->default('starter'); // starter|growth|enterprise
            }
            if (! Schema::hasColumn('merchants', 'subscription_status')) {
                $table->string('subscription_status', 32)->default('active'); // active|past_due|cancelled|deactivated
            }
            if (! Schema::hasColumn('merchants', 'deactivated_at')) {
                $table->timestamp('deactivated_at')->nullable();
            }
            if (! Schema::hasColumn('merchants', 'purge_after')) {
                $table->timestamp('purge_after')->nullable(); // 90 days after deactivate
            }
            if (! Schema::hasColumn('merchants', 'show_in_ethical_directory')) {
                $table->boolean('show_in_ethical_directory')->default(false);
            }
            if (! Schema::hasColumn('merchants', 'ethical_badge_enabled')) {
                $table->boolean('ethical_badge_enabled')->default(true);
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'luvi_fee_amount')) {
                $table->unsignedBigInteger('luvi_fee_amount')->default(0);
            }
            if (! Schema::hasColumn('transactions', 'fee_type')) {
                $table->string('fee_type', 40)->nullable(); // resale_marketplace|prevention_fee|saas_subscription
            }
        });

        Schema::create('monthly_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->string('tier', 32);
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('USD');
            $table->string('status', 32)->default('open'); // open|paid|void|past_due
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('line_items')->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'period']);
        });

        Schema::create('user_trust_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('merchant_user_id');
            $table->unsignedTinyInteger('score')->default(100); // 0-100
            $table->unsignedInteger('forgotten_cancel_count')->default(0);
            $table->unsignedInteger('resale_count_30d')->default(0);
            $table->unsignedInteger('second_chance_count')->default(0);
            $table->boolean('resale_eligible')->default(true);
            $table->boolean('second_chance_eligible')->default(true);
            $table->json('flags')->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'merchant_user_id']);
        });

        Schema::create('ethical_awards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('title');
            $table->text('citation')->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'year', 'title']);
        });

        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider')->default('paypal');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 32)->default('running'); // running|ok|discrepancy|failed
            $table->unsignedInteger('checked')->default(0);
            $table->unsignedInteger('mismatches')->default(0);
            $table->json('report')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_runs');
        Schema::dropIfExists('ethical_awards');
        Schema::dropIfExists('user_trust_scores');
        Schema::dropIfExists('monthly_invoices');
        Schema::table('transactions', function (Blueprint $table) {
            foreach (['luvi_fee_amount', 'fee_type'] as $c) {
                if (Schema::hasColumn('transactions', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
        Schema::table('merchants', function (Blueprint $table) {
            foreach (['subscription_tier', 'subscription_status', 'deactivated_at', 'purge_after', 'show_in_ethical_directory', 'ethical_badge_enabled'] as $c) {
                if (Schema::hasColumn('merchants', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
