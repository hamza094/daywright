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
            $table->string('sync_operation_type')->nullable()->after('sync_operation_id');
            $table->longText('sync_payload')->nullable()->after('sync_operation_type');
        });
    }
};
