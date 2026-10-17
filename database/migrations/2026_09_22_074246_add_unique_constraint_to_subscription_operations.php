<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('subscription_operations', function (Blueprint $table) {
            // Drop the existing non-unique index
            $table->dropIndex(['idempotency_key_hash', 'user_id']);

            // Add unique constraint
            $table->unique(['user_id', 'idempotency_key_hash']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('subscription_operations', function (Blueprint $table) {
            // Drop the unique constraint
            $table->dropUnique(['user_id', 'idempotency_key_hash']);

            // Restore the non-unique index
            $table->index(['idempotency_key_hash', 'user_id']);
        });
    }
};
