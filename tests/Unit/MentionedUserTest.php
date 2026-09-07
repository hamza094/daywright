<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentionedUserTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_wraps_mentioned_usernames_in_the_body_within_anchor_tag(): void
    {
        $conversation = Conversation::factory()->create(['message' => 'Hello @johnathan77']);

        $this->assertEquals(
            'Hello <a href="/user/johnathan77/profile" target="_blank">@johnathan77</a>',
            $conversation->message
        );
    }
}
