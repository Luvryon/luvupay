<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('url');
            $table->text('secret');                         // encrypted via cast
            $table->text('previous_secret')->nullable();
            $table->timestamp('previous_secret_expires_at')->nullable();
            $table->json('events')->nullable();            // e.g. ["entitlement.transfer", "subscription.cancelled"]
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
