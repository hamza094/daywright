<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string OLD_INDEX = 'notifications_notifiable_type_id_read_created_id_index';

    private const string NEW_INDEX = 'notifications_notifiable_type_id_read_created_id_desc_idx';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if ($this->hasIndex(self::OLD_INDEX)) {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->dropIndex(self::OLD_INDEX);
            });
        }

        if (! $this->hasIndex(self::NEW_INDEX)) {
            DB::statement(
                'CREATE INDEX '.self::NEW_INDEX.' ON notifications (notifiable_type, notifiable_id, read_at, created_at DESC, id DESC)'
            );
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

        if (! $this->hasIndex(self::OLD_INDEX)) {
            DB::statement(
                'CREATE INDEX '.self::OLD_INDEX.' ON notifications (notifiable_type, notifiable_id, read_at, created_at DESC, id DESC)'
            );
        }
    }

    private function hasIndex(string $indexName): bool
    {
        return collect(Schema::getIndexes('notifications'))
            ->contains(fn (array $index): bool => $index['name'] === $indexName);
    }
};
