<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'notifications_user_created_idx';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! $this->hasIndex(self::INDEX_NAME)) {
            DB::statement(
                'CREATE INDEX '.self::INDEX_NAME.' ON notifications (notifiable_type, notifiable_id, created_at DESC, id DESC)'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->hasIndex(self::INDEX_NAME)) {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->dropIndex(self::INDEX_NAME);
            });
        }
    }

    private function hasIndex(string $indexName): bool
    {
        return collect(Schema::getIndexes('notifications'))
            ->contains(fn (array $index): bool => $index['name'] === $indexName);
    }
};
