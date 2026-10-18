<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
                $table->foreignUuid('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
                $table->string('invoice_number', 32)->unique();
                $table->string('billing_name')->nullable();
                $table->text('billing_address')->nullable();
                $table->string('tax_id', 64)->nullable();
                $table->unsignedBigInteger('subtotal_minor');
                $table->unsignedBigInteger('tax_amount_minor')->default(0);
                $table->unsignedBigInteger('total_minor');
                $table->string('currency', 3)->default('USD');
                $table->string('status', 16)->default('paid'); // paid|pending|void
                $table->string('pdf_url')->nullable();
                $table->json('line_items')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
                $table->index(['merchant_id', 'issued_at']);
            });
        }

        if (! Schema::hasTable('webhook_deliveries')) {
            Schema::create('webhook_deliveries', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
                $table->foreignUuid('webhook_endpoint_id')->nullable();
                $table->string('event_type', 96);
                $table->json('payload');
                $table->unsignedSmallInteger('response_code')->nullable();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->timestamp('next_retry_at')->nullable();
                $table->string('status', 16)->default('pending'); // success|failed|pending
                $table->text('last_error')->nullable();
                $table->string('target_url')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();
                $table->index(['merchant_id', 'status']);
                $table->index(['status', 'next_retry_at']);
            });
        }

        Schema::table('merchants', function (Blueprint $table) {
            if (! Schema::hasColumn('merchants', 'api_monthly_limit')) {
                $table->unsignedInteger('api_monthly_limit')->default(1000)->after('is_active');
            }
            if (! Schema::hasColumn('merchants', 'billing_address')) {
                $table->text('billing_address')->nullable();
            }
            if (! Schema::hasColumn('merchants', 'tax_id')) {
                $table->string('tax_id', 64)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('invoices');
        Schema::table('merchants', function (Blueprint $table) {
            foreach (['api_monthly_limit', 'billing_address', 'tax_id'] as $c) {
                if (Schema::hasColumn('merchants', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
