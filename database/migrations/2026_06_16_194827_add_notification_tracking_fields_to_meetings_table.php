<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class AddNotificationTrackingFieldsToMeetingsTable extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->timestamp('started_notification_sent_at')->nullable();
            $table->timestamp('ended_notification_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['started_notification_sent_at', 'ended_notification_sent_at']);
        });
    }
}
