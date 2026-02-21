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
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false)->index()->after('email_verified_at');
            $table->timestamp('admin_granted_at')->nullable()->after('is_admin');
            $table->foreignId('admin_granted_by')->nullable()->after('admin_granted_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('admin_granted_by');
            $table->dropColumn(['is_admin', 'admin_granted_at']);
        });
    }
};
