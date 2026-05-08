<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_members', function (Blueprint $table): void {
            $table->dropIndex('project_members_project_id_user_id_index');
            $table->unique(['project_id', 'user_id'], 'project_members_project_id_user_id_unique');
        });

        Schema::table('task_user', function (Blueprint $table): void {
            $table->dropIndex('task_user_user_id_task_id_index');
            $table->unique(['user_id', 'task_id'], 'task_user_user_id_task_id_unique');
        });

        Schema::table('message_user', function (Blueprint $table): void {
            $table->unique(['message_id', 'user_id'], 'message_user_message_id_user_id_unique');
        });

        Schema::table('meetings', function (Blueprint $table): void {
            $table->unique('meeting_id', 'meetings_meeting_id_unique');
        });
    }
};
