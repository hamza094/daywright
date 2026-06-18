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
        Schema::create('meetings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index('meetings_user_id_foreign');
            $table->unsignedBigInteger('project_id')->index('meetings_project_id_foreign');
            $table->unsignedBigInteger('meeting_id')->nullable()->index();
            $table->string('topic')->nullable();
            $table->string('agenda')->nullable();
            $table->integer('duration');
            $table->string('password');
            $table->string('join_url', 2000)->nullable();
            $table->string('start_url', 2000)->nullable();
            $table->dateTime('start_time');
            $table->string('status')->nullable();
            $table->boolean('join_before_host');
            $table->string('timezone')->nullable();
            $table->timestamps();
            $table->boolean('is_programmatic_update')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
