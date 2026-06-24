<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Preflight check: ensure no duplicate subscriptions exist before adding unique constraint
        $duplicates = DB::table('subscriptions')
            ->select('billable_type', 'billable_id', 'name', DB::raw('COUNT(*) as count'))
            ->groupBy('billable_type', 'billable_id', 'name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot add unique constraint: duplicate subscriptions found. '.
                'Please manually reconcile the following duplicates before running this migration: '.json_encode($duplicates)
            );
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unique(['billable_type', 'billable_id', 'name'], 'subscriptions_unique_name_per_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique('subscriptions_unique_name_per_user');
        });
    }
};
