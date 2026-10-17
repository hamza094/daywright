<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->timestamp('started_notification_pending_at')->nullable()->index();
            $table->timestamp('ended_notification_pending_at')->nullable()->index();
        });
    }
};
