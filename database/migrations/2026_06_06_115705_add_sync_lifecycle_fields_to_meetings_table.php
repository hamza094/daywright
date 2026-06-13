<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSyncLifecycleFieldsToMeetingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('sync_status')->default('active')->index();
            $table->text('sync_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->unsignedInteger('sync_attempts')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropIndex(['sync_status']);
            $table->dropColumn(['sync_status', 'sync_error', 'synced_at', 'sync_attempts']);
        });
    }
}
