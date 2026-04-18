<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(
            'CREATE INDEX tasks_user_deleted_created_id_index ON tasks (user_id, deleted_at, created_at DESC, id DESC)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX tasks_user_deleted_created_id_index ON tasks');
    }
};
