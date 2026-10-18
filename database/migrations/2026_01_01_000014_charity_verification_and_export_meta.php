<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charity_wallets', function (Blueprint $table) {
            if (! Schema::hasColumn('charity_wallets', 'verification_status')) {
                $table->string('verification_status', 32)->default('pending'); // pending|verified|rejected
            }
            if (! Schema::hasColumn('charity_wallets', 'legal_name')) {
                $table->string('legal_name')->nullable();
            }
            if (! Schema::hasColumn('charity_wallets', 'registration_id')) {
                $table->string('registration_id')->nullable();
            }
            if (! Schema::hasColumn('charity_wallets', 'verified_at')) {
                $table->timestamp('verified_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('charity_wallets', function (Blueprint $table) {
            foreach (['verification_status', 'legal_name', 'registration_id', 'verified_at'] as $c) {
                if (Schema::hasColumn('charity_wallets', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
