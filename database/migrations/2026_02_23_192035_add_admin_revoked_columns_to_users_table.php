<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('admin_revoked_at')->nullable()->after('admin_granted_by');
            $table->foreignId('admin_revoked_by')->nullable()->after('admin_revoked_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('admin_revoked_by');
            $table->dropColumn('admin_revoked_at');
        });
    }
};
