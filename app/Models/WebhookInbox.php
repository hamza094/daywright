<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WebhookInboxState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookInbox extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'payload',
        'claim_token',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'payload' => 'encrypted:array',
        'state' => WebhookInboxState::class,
        'provider_occurred_at' => 'datetime',
        'available_at' => 'datetime',
        'claimed_at' => 'datetime',
        'claim_expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];
}
