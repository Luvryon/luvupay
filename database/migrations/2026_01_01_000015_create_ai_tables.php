<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_request_logs')) {
            Schema::create('ai_request_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('feature_type', 64);
                $table->string('input_hash', 64)->index();
                $table->json('request_payload')->nullable();
                $table->json('response_payload')->nullable();
                $table->unsignedInteger('tokens_used')->nullable();
                $table->decimal('cost_usd', 12, 6)->nullable();
                $table->unsignedInteger('latency_ms')->nullable();
                $table->string('status', 32)->default('pending');
                $table->text('error_message')->nullable();
                $table->uuid('merchant_id')->nullable()->index();
                $table->timestamps();
                $table->index(['feature_type', 'created_at']);
            });
        }

        if (! Schema::hasTable('ai_cached_responses')) {
            Schema::create('ai_cached_responses', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('feature_type', 64);
                $table->string('input_hash', 64);
                $table->json('response_payload');
                $table->timestamp('expires_at')->index();
                $table->timestamps();
                $table->unique(['feature_type', 'input_hash']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_cached_responses');
        Schema::dropIfExists('ai_request_logs');
    }
};
