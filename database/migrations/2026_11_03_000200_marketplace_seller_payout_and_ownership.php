<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            // Automatic seller payout after a sale (PayPal Payouts)
            $table->string('payout_status', 24)->nullable()->index();   // null | held_unverified | sending | sent | paid | failed
            $table->unsignedBigInteger('payout_minor')->nullable();
            $table->string('payout_sender_batch_id', 120)->nullable();
            $table->string('payout_batch_id', 120)->nullable();
            $table->string('payout_batch_status', 40)->nullable();
            $table->boolean('payout_simulated')->default(false);
            $table->string('payout_error', 500)->nullable();
            $table->unsignedSmallInteger('payout_attempts')->default(0);
            $table->timestamp('paid_out_at')->nullable();

            // Ownership signal: set ONLY when the seller really clicked the signed link sent to the payout address.
            $table->string('ownership_level', 32)->nullable();          // null | email_signed_link
            $table->json('ownership_evidence')->nullable();             // when, hashed ip/ua, email fingerprint
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            $table->dropColumn(['payout_status', 'payout_minor', 'payout_sender_batch_id', 'payout_batch_id', 'payout_batch_status',
                'payout_simulated', 'payout_error', 'payout_attempts', 'paid_out_at', 'ownership_level', 'ownership_evidence']);
        });
    }
};
