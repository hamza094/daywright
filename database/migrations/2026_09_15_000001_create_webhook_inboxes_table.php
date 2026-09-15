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
        Schema::create('webhook_inboxes', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->char('event_key', 64);
            $table->string('event_type', 100);
            $table->string('provider_request_id', 255)->nullable();
            $table->unsignedBigInteger('provider_occurred_at')->nullable();
            $table->longText('payload');
            $table->string('state', 20)->default('received');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->char('claim_token', 36)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('claim_expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('last_error_class', 255)->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_key']);
            $table->index(['state', 'available_at']);
            $table->index(['state', 'claim_expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_inboxes');
    }
};
