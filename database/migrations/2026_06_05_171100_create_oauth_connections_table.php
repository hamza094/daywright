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
        Schema::create('oauth_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('expires_at');
            $table->string('external_user_id')->nullable();
            $table->json('scopes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
        });

        // Backfill existing Zoom oauth details from users table
        DB::table('users')
            ->whereNotNull('zoom_access_token')
            ->whereNotNull('zoom_refresh_token')
            ->whereNotNull('zoom_expires_at')
            ->orderBy('id')
            ->chunk(100, function ($users): void {
                foreach ($users as $user) {
                    DB::table('oauth_connections')->insert([
                        'user_id' => $user->id,
                        'provider' => 'zoom',
                        'access_token' => $user->zoom_access_token,
                        'refresh_token' => $user->zoom_refresh_token,
                        'expires_at' => $user->zoom_expires_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_connections');
    }
};
