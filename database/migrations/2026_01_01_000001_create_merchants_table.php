<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('api_key_prefix', 16)->nullable()->index();
            $table->string('api_key_hash', 64)->nullable()->unique();
            $table->string('publishable_key', 48)->nullable()->unique();
            $table->json('allowed_origins')->nullable();
            // Split in basis points (must sum to 10000)
            $table->unsignedSmallInteger('split_user_bps')->default(3000);
            $table->unsignedSmallInteger('split_charity_bps')->default(2000);
            $table->unsignedSmallInteger('split_merchant_bps')->default(5000);
            $table->unsignedSmallInteger('second_chance_window_hours')->default(48);
            $table->unsignedSmallInteger('nudge_lead_hours')->default(72);
            $table->unsignedSmallInteger('serial_forgetter_threshold')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
