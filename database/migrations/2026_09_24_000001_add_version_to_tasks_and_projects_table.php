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
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('notify_sent');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('health_score_calculated_at');
        });
    }
};
