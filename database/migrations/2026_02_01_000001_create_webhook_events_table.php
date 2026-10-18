<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Durable inbox for provider webhooks: de-duplication, retry state, dead-letter and replay. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider', 32);
            $table->string('event_id', 191);
            $table->string('event_type', 96);
            // received -> processing -> processed | failed (will retry) | dead (retries exhausted) | ignored
            $table->string('status', 16)->default('received');
            $table->longText('payload');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);          // authoritative de-dupe
            $table->index(['status', 'next_retry_at']);
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
