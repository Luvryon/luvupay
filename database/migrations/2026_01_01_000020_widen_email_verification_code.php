<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('merchant_users')) {
            return;
        }
        // Argon2id hashes are ~96+ chars; column was 12 (OTP plain length only).
        DB::statement('ALTER TABLE merchant_users MODIFY email_verification_code VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE merchant_users MODIFY email_verification_code VARCHAR(12) NULL');
    }
};
