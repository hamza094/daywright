<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->timestamp('sync_reconcile_before_at')->nullable()->after('sync_started_at');
        });

        DB::table('meetings')
            ->whereNotNull('synced_at')
            ->update(['sync_reconcile_before_at' => DB::raw('synced_at')]);
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->dropColumn('sync_reconcile_before_at');
        });
    }
};
