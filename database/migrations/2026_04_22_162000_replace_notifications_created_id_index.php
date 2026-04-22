<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NEW_INDEX = 'notifications_user_created_idx';

    private const OLD_COLUMNS_SIGNATURE = 'notifiable_type,notifiable_id,created_at,id';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Find any index that exactly matches the 4-column signature and drop it
        $rows = DB::select(
            "SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications'
             GROUP BY INDEX_NAME
             HAVING cols = ?",
            [self::OLD_COLUMNS_SIGNATURE]
        );

        foreach ($rows as $row) {
            $indexName = $row->INDEX_NAME ?? $row->index_name ?? null;

            if (! $indexName) {
                continue;
            }

            if ($this->hasIndex($indexName)) {
                Schema::table('notifications', function (Blueprint $table) use ($indexName): void {
                    $table->dropIndex($indexName);
                });
            } else {
                // Fallback raw drop
                DB::statement('DROP INDEX '.$indexName.' ON notifications');
            }
        }

        // Create the new DESC index if it doesn't exist
        if (! $this->hasIndex(self::NEW_INDEX)) {
            DB::statement('CREATE INDEX '.self::NEW_INDEX.' ON notifications (notifiable_type, notifiable_id, created_at DESC, id DESC)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->hasIndex(self::NEW_INDEX)) {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->dropIndex(self::NEW_INDEX);
            });
        }

        // Recreate a conventional non-DESC 4-column index (best-effort)
        if (! $this->hasIndex('notifications_notifiable_type_id_created_id_index')) {
            DB::statement('CREATE INDEX notifications_notifiable_type_id_created_id_index ON notifications (notifiable_type, notifiable_id, created_at, id)');
        }
    }

    private function hasIndex(string $indexName): bool
    {
        return collect(Schema::getIndexes('notifications'))
            ->contains(fn (array $index): bool => $index['name'] === $indexName);
    }
};
