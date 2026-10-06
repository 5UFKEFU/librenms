<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-service check interval, and how many failed checks in a row are
     * needed before the service is marked failed (and alerts fire).
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->unsignedInteger('service_interval')->nullable()->after('service_checked');
            $table->unsignedTinyInteger('service_retries')->default(1)->after('service_interval');
            $table->unsignedTinyInteger('service_fail_count')->default(0)->after('service_retries');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['service_interval', 'service_retries', 'service_fail_count']);
        });
    }
};
