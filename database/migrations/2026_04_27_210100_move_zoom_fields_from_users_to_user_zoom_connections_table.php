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
        $timestamp = now();

        $existingConnections = DB::table('users')
            ->where(function ($query): void {
                $query->whereNotNull('zoom_access_token')
                    ->orWhereNotNull('zoom_refresh_token')
                    ->orWhereNotNull('zoom_expires_at');
            })
            ->get(['id', 'zoom_access_token', 'zoom_refresh_token', 'zoom_expires_at'])
            ->map(fn ($user): array => [
                'user_id' => $user->id,
                'access_token' => $user->zoom_access_token,
                'refresh_token' => $user->zoom_refresh_token,
                'expires_at' => $user->zoom_expires_at,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])
            ->all();

        if ($existingConnections !== []) {
            DB::table('user_zoom_connections')->upsert(
                $existingConnections,
                ['user_id'],
                ['access_token', 'refresh_token', 'expires_at', 'updated_at']
            );
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'zoom_access_token',
                'zoom_refresh_token',
                'zoom_expires_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('zoom_access_token', 2000)->nullable();
            $table->string('zoom_refresh_token', 2000)->nullable();
            $table->dateTime('zoom_expires_at')->nullable();
        });

        $zoomConnections = DB::table('user_zoom_connections')
            ->get(['user_id', 'access_token', 'refresh_token', 'expires_at']);

        foreach ($zoomConnections as $zoomConnection) {
            DB::table('users')
                ->where('id', $zoomConnection->user_id)
                ->update([
                    'zoom_access_token' => $zoomConnection->access_token,
                    'zoom_refresh_token' => $zoomConnection->refresh_token,
                    'zoom_expires_at' => $zoomConnection->expires_at,
                ]);
        }
    }
};
