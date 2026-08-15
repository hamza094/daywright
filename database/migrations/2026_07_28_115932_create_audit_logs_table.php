<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Context Tracking
            $table->string('actor_type', 50)->default('system');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('key_id')->nullable();

            // Event & Target
            $table->string('event');
            $table->nullableMorphs('auditable');

            // State Changes & Context
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();

            // Strictly Append-Only (No updated_at)
            $table->timestamp('created_at')->useCurrent();

            // Indexes for fast querying
            $table->index('event');
            $table->index(['actor_type', 'actor_id']);
            $table->index('key_id');
        });
    }
};
