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
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('uuid', 36)->nullable()->unique();
            $table->string('name', 100);
            $table->string('username', 50)->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('timezone')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->string('oauth_id')->nullable();
            $table->string('oauth_provider')->nullable();
            $table->string('oauth_token', 1000)->nullable();
            $table->string('oauth_refresh_token')->nullable();
            $table->dateTime('last_active_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
