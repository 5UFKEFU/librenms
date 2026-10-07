<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A response-time threshold per service, kept apart from up/down: a slow
     * answer sets service_slow (after service_retries slow checks in a row)
     * without changing service_status, so alert rules can tell "slow" from "down".
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->float('service_slow_after')->nullable()->after('service_fail_count');
            $table->unsignedTinyInteger('service_slow_count')->default(0)->after('service_slow_after');
            $table->boolean('service_slow')->default(false)->after('service_slow_count');
            $table->float('service_response_time')->nullable()->after('service_slow');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['service_slow_after', 'service_slow_count', 'service_slow', 'service_response_time']);
        });
    }
};
