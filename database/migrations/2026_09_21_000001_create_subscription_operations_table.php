<?php

declare(strict_types=1);

use App\Enums\Subscription\SubscriptionOperationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_operations', function (Blueprint $table) {
            $table->id();
            $table->char('operation_uuid', 36)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->string('paddle_subscription_id', 64)->nullable();
            $table->string('type', 20);
            $table->string('target_plan', 50)->nullable();
            $table->char('idempotency_key_hash', 64);
            $table->char('request_fingerprint', 64);
            $table->string('status', 20)->default(SubscriptionOperationStatus::Pending->value);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->char('claim_token', 36)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('claim_expires_at')->nullable();
            $table->timestamp('provider_attempted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->string('last_error_class', 255)->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('paddle_subscription_id');
            $table->index(['idempotency_key_hash', 'user_id']);
            $table->index(['status', 'available_at']);
            $table->index(['status', 'claim_expires_at']);
        });
    }
};
