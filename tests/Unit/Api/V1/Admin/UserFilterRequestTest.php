<?php

declare(strict_types=1);

namespace Tests\Unit\Api\V1\Admin;

use App\Http\Requests\Api\V1\Admin\UserFilterRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Tests\TestCase;

final class UserFilterRequestTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_exposes_the_phase_two_spatie_conventions_for_admin_users(): void
    {
        $allowedFilters = UserFilterRequest::allowedFilters();
        $allowedSorts = UserFilterRequest::allowedSorts();

        $this->assertCount(1, $allowedFilters);
        $this->assertInstanceOf(AllowedFilter::class, $allowedFilters[0]);
        $this->assertSame('search', $allowedFilters[0]->getName());

        $this->assertCount(3, $allowedSorts);
        $this->assertContainsOnlyInstancesOf(AllowedSort::class, $allowedSorts);
        $this->assertSame(['created_at', 'name', 'email'], array_map(
            static fn (AllowedSort $allowedSort): string => $allowedSort->getName(),
            $allowedSorts,
        ));
        $this->assertSame(['-created_at'], UserFilterRequest::defaultSorts());
        $this->assertSame([], UserFilterRequest::allowedIncludes());
    }

    #[Test]
    public function it_uses_the_search_allowlist_to_match_name_username_and_email(): void
    {
        $nameMatch = User::factory()->create([
            'name' => 'Alpha Search User',
            'username' => 'alpha-user',
            'email' => 'alpha@example.com',
        ]);
        $usernameMatch = User::factory()->create([
            'name' => 'Bravo User',
            'username' => 'search-target',
            'email' => 'bravo@example.com',
        ]);
        $emailMatch = User::factory()->create([
            'name' => 'Charlie User',
            'username' => 'charlie-user',
            'email' => 'searchable@example.com',
        ]);
        $nonMatch = User::factory()->create([
            'name' => 'Delta User',
            'username' => 'delta-user',
            'email' => 'delta@example.com',
        ]);

        $query = User::query();
        $allowedFilter = UserFilterRequest::allowedFilters()[0];
        $allowedFilter->applyTo($query, 'search');

        $matchingIds = $query->pluck('id')->all();

        $this->assertContains($nameMatch->id, $matchingIds);
        $this->assertContains($usernameMatch->id, $matchingIds);
        $this->assertContains($emailMatch->id, $matchingIds);
        $this->assertNotContains($nonMatch->id, $matchingIds);
    }
}
