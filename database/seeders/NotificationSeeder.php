<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Previous implementation (kept for reference):
        // This calls ->notify() which can dispatch via queue depending on notification/channel config.
        //
        // $projects = Project::with('tasks', 'members', 'user')->get();
        // $projects->each(function ($project) {
        //     $project->tasks->each(function ($task) use ($project) {
        //         foreach ($project->members as $member) {
        //             $member->notify(new ProjectTask(
        //                 $project->name,
        //                 $project->path(),
        //                 $project->user->getNotifierData()
        //             ));
        //         }
        //     });
        // });

        // New implementation: insert notifications directly into the DB (no queue).
        // The data payload should match the actual ProjectInvitation notification:
        // [ 'message' => string, 'notifier' => array, 'link' => string ]
        $projects = Project::query()
            ->with(['user:id,uuid,name,username,avatar_path,email'])
            ->get(['id', 'name', 'slug', 'user_id']);

        $now = now();

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunk(200, function ($users) use ($projects, $now) {
                foreach ($users as $user) {
                    $rows = [];

                    for ($i = 0; $i < 50; $i++) {
                        $project = $projects->isNotEmpty()
                            ? $projects->random()
                            : null;

                        $projectName = $project?->name ?? 'Unknown project';
                        $projectPath = $project ? $project->path() : '/api/v1/projects/'.Str::uuid();
                        $notifierData = $project?->user?->getNotifierData() ?? $user->getNotifierData();

                        $rows[] = [
                            'id' => (string) Str::uuid(),
                            'type' => 'App\\Notifications\\ProjectInvitation',
                            'notifiable_type' => User::class,
                            'notifiable_id' => (string) $user->id,
                            'data' => json_encode([
                                'message' => 'Sent you a project '.$projectName.' invitation',
                                'notifier' => $notifierData,
                                'link' => $projectPath,
                            ]),
                            'read_at' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                            'signature' => Str::random(64),
                        ];
                    }

                    DB::table('notifications')->insert($rows);
                }
            });
    }
}
