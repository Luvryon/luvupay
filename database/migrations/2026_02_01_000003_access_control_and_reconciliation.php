<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->json('api_scopes')->nullable();                       // null = every scope
            $table->unsignedInteger('rate_limit_per_minute')->nullable(); // null = platform default
        });

        Schema::table('merchant_users', function (Blueprint $table) {
            $table->string('role', 16)->default('owner');                 // owner|admin|finance|developer|viewer
            $table->text('mfa_secret')->nullable();                       // encrypted (Crypt)
            $table->timestamp('mfa_enabled_at')->nullable();
            $table->text('mfa_recovery_codes')->nullable();               // encrypted JSON of hashed codes
            $table->unsignedBigInteger('mfa_last_step')->nullable();      // replay protection for TOTP
        });

        Schema::create('team_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 16);
            $table->string('token_hash', 64)->unique();
            $table->uuid('invited_by')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['merchant_id', 'email']);
        });

        Schema::create('reconciliation_exceptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('run_id')->constrained('reconciliation_runs')->cascadeOnDelete();
            $table->uuid('transaction_id')->nullable()->index();
            $table->string('issue', 64);
            $table->json('detail')->nullable();
            $table->string('status', 16)->default('open');                // open | resolved | auto_resolved
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'issue']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_exceptions');
        Schema::dropIfExists('team_invitations');
        Schema::table('merchant_users', function (Blueprint $table) {
            $table->dropColumn(['role', 'mfa_secret', 'mfa_enabled_at', 'mfa_recovery_codes', 'mfa_last_step']);
        });
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn(['api_scopes', 'rate_limit_per_minute']);
        });
    }
};
