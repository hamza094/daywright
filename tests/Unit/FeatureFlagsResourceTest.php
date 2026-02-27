<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\FeatureFlag;
use App\Http\Resources\Api\V1\FeatureFlagsResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeatureFlagsResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_default_flags_for_non_user_resource(): void
    {
        $resource = new FeatureFlagsResource(null);

        $result = $resource->toArray(request());

        $this->assertSame([], $result);
    }

    #[Test]
    public function it_returns_only_active_visible_flags_for_non_admin_users(): void
    {
        $user = User::factory()->create();

        Feature::for($user)->activate(FeatureFlag::ProjectExport->pennantName());
        Feature::for($user)->deactivate(FeatureFlag::ProjectMessaging->pennantName());

        $resource = new FeatureFlagsResource($user);

        $result = $resource->toArray(request());

        $this->assertSame([
            FeatureFlag::ProjectExport->key() => true,
        ], $result);
    }

    #[Test]
    public function it_returns_full_map_for_admin_users(): void
    {
        $user = User::factory()->admin()->create();

        Feature::for($user)->activate(FeatureFlag::ProjectExport->pennantName());
        Feature::for($user)->deactivate(FeatureFlag::ProjectMessaging->pennantName());

        $resource = new FeatureFlagsResource($user);

        $result = $resource->toArray(request());

        $expectedKeys = array_map(
            fn (FeatureFlag $flag) => $flag->key(),
            FeatureFlag::cases()
        );

        $this->assertSame($expectedKeys, array_keys($result));
        $this->assertTrue($result[FeatureFlag::ProjectExport->key()]);
        $this->assertFalse($result[FeatureFlag::ProjectMessaging->key()]);
    }
}
