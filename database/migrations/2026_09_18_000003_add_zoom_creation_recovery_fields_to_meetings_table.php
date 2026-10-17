<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->uuid('sync_operation_id')->nullable()->unique()->after('id');
            $table->uuid('sync_claim_token')->nullable()->after('sync_operation_id');
            $table->timestamp('sync_started_at')->nullable()->after('sync_claim_token');
            $table->timestamp('sync_lease_expires_at')->nullable()->after('sync_started_at');
            $table->timestamp('sync_available_at')->nullable()->after('sync_lease_expires_at');

            $table->index(['sync_status', 'sync_available_at']);
            $table->index(['sync_status', 'sync_lease_expires_at']);
        });
    }
};
