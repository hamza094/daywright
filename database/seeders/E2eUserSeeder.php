<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Deterministic accounts for the Playwright E2E smoke suite.
 *
 * This seeder is intentionally NOT registered in DatabaseSeeder so known
 * credentials are never created by a routine `db:seed` (e.g. production).
 * Run it explicitly on local/staging before `npm run test:e2e`:
 *
 *   php artisan db:seed --class=E2eUserSeeder
 *
 * Credentials default to the values the specs fall back to and can be
 * overridden via E2E_USER_EMAIL, E2E_USER_B_EMAIL and E2E_USER_PASSWORD.
 */
class E2eUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make((string) env('E2E_USER_PASSWORD', 'Berry@999'));

        $userA = User::updateOrCreate(
            ['email' => (string) env('E2E_USER_EMAIL', 'e2e-user-a@daywright.test')],
            [
                'name' => 'E2E User A',
                'username' => 'e2e_user_a',
                'avatar_path' => 'https://eu.ui-avatars.com/api/?name=E2E+User+A',
                'email_verified_at' => now(),
                'password' => $password,
            ],
        );

        // User B lives outside User A's projects so the tenant-isolation spec
        // can assert a 403/404 on cross-tenant project access.
        User::updateOrCreate(
            ['email' => (string) env('E2E_USER_B_EMAIL', 'e2e-user-b@daywright.test')],
            [
                'name' => 'E2E User B',
                'username' => 'e2e_user_b',
                'avatar_path' => 'https://eu.ui-avatars.com/api/?name=E2E+User+B',
                'email_verified_at' => now(),
                'password' => $password,
            ],
        );

        // User A needs at least one dashboard project card: the error-handling
        // spec navigates to the first project and creates a fixture task if
        // the project has none.
        $stageId = Stage::query()->orderBy('id')->value('id') ?? Stage::factory()->create()->id;

        foreach (['e2e-smoke-project-alpha', 'e2e-smoke-project-beta'] as $slug) {
            Project::firstOrCreate(
                ['slug' => $slug],
                [
                    'user_id' => $userA->id,
                    'stage_id' => $stageId,
                    'name' => 'E2E Smoke Project',
                    'about' => 'Deterministic fixture project for Playwright E2E smoke tests.',
                    'stage_updated_at' => now(),
                ],
            );
        }
    }
}
