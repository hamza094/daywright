<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Http\Requests\Api\V1\Admin\UserFilterRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class LiteralSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function literalPrefixes(): array
    {
        return [
            'percent' => ['client%', 'clientX'],
            'underscore' => ['client_', 'clientX'],
            'escape character' => ['client!', 'client'],
            'backslash' => ['client\\', 'client'],
        ];
    }

    #[DataProvider('literalPrefixes')]
    public function test_search_treats_special_characters_as_literals(string $prefix, string $otherPrefix): void
    {
        $matchingUser = User::factory()->create([
            'name' => 'Matching User',
            'username' => $prefix.'team',
            'email' => 'matching@example.test',
        ]);
        User::factory()->create([
            'name' => 'Other User',
            'username' => $otherPrefix.'team',
            'email' => 'other@example.test',
        ]);

        $query = User::query();
        UserFilterRequest::allowedFilters()[0]->applyTo($query, $prefix);

        $this->assertSame([$matchingUser->id], $query->pluck('id')->all());
    }
}
