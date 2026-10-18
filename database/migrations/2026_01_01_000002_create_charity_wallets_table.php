<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charity_wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('payout_email');
            $table->string('currency', 3)->default('USD');
            $table->unsignedBigInteger('total_received_minor')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charity_wallets');
    }
};
