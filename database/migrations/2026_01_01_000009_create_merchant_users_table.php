<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('password'); // Argon2id via hasher
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email_verification_code', 255)->nullable();
            $table->timestamp('email_verification_expires_at')->nullable();
            $table->boolean('is_owner')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->unique(['merchant_id', 'email']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_users');
    }
};
