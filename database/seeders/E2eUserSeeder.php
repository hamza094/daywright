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
 *
 * Safety: refuses to run in production. Because updateOrCreate keys on the
 * configured email, never point E2E_USER_EMAIL at a real user account.
 */
class E2eUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('E2eUserSeeder must never run in production.');

            return;
        }

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

        $this->seedFixtureProjects($userA);
    }

    /**
     * Reset User A's test projects and create fresh fixtures.
     *
     * Project slugs are regenerated from `name` by the Sluggable package
     * (onUpdate: true) and soft-deleted rows keep their slug reserved, so
     * keying fixtures on slug is unreliable. Instead we remove every project
     * the E2E suite may have created for this dedicated test account — both
     * seeder fixtures and spec-created "Smoke Project ..." rows, including
     * trashed ones — then recreate a clean pair. The free plan allows only
     * 3 owned projects, so leftovers would break subsequent runs.
     */
    private function seedFixtureProjects(User $userA): void
    {
        Project::withTrashed()
            ->where('user_id', $userA->id)
            ->where(function ($query): void {
                $query->where('name', 'like', 'E2E Smoke Project %')
                    ->orWhere('name', 'like', 'Smoke Project %');
            })
            ->forceDelete();

        $stageId = Stage::query()->orderBy('id')->value('id');
        if ($stageId === null) {
            $this->command?->error('No stages found. Run `php artisan db:seed` first.');

            return;
        }

        foreach (['Alpha', 'Beta'] as $suffix) {
            Project::factory()->create([
                'user_id' => $userA->id,
                'stage_id' => $stageId,
                'name' => "E2E Smoke Project {$suffix}",
                'about' => 'Deterministic fixture project for Playwright E2E smoke tests.',
            ]);
        }
    }
}
