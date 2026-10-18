<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('app_key', 64);
            $table->string('app_name', 120);
            $table->string('title', 255);
            $table->text('description');
            $table->string('seller_email', 190);
            $table->unsignedSmallInteger('days_remaining');
            $table->unsignedBigInteger('original_price_minor');
            $table->unsignedBigInteger('price_minor');
            $table->string('currency', 3)->default('USD');
            $table->string('status', 32)->default('active'); // active|reserved|sold|cancelled
            $table->string('paypal_order_id', 64)->nullable();
            $table->string('buyer_email', 190)->nullable();
            $table->string('emoji', 16)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('seller_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_listings');
    }
};
