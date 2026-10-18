<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->string('pending_url')->nullable()->after('url');
            $table->timestamp('pending_url_activates_at')->nullable()->after('pending_url');
            $table->timestamp('url_locked_until')->nullable()->after('pending_url_activates_at');
            $table->string('change_confirmation_code', 12)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->dropColumn(['pending_url', 'pending_url_activates_at', 'url_locked_until', 'change_confirmation_code']);
        });
    }
};
