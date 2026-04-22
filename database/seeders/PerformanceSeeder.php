<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seeds ~1.1M rows of realistic data for performance testing.
 *
 * Usage: php artisan migrate:fresh --seeder=PerformanceSeeder
 *    or: php artisan db:seed --class=PerformanceSeeder
 *
 * All tables are truncated before seeding to ensure predictable auto-increment IDs.
 */
class PerformanceSeeder extends Seeder
{
    // ── Volume knobs ────────────────────────────────────────
    private const USERS = 10000;

    private const PROJECTS_PER_USER = 5;

    private const TASKS_PER_PROJECT = 10;

    private const CONVERSATIONS_PER_PROJECT = 75;

    private const ACTIVITIES_PER_PROJECT = 80;

    private const MESSAGES_PER_PROJECT = 3;

    private const MEETINGS_PER_PROJECT = 6;

    private const MEMBERS_PER_PROJECT_MAX = 6;

    private const NOTIFICATIONS_PER_USER = 35;

    // ── Internals ───────────────────────────────────────────
    private const CHUNK_SIZE = 1000;

    private const USER_BATCH_SIZE = 50;

    private string $hashedPassword;

    private string $nowString;

    private int $nowTs;

    /** @var array<int, string> */
    private array $sentencePool = [];

    /** @var array<int, string> */
    private array $paragraphPool = [];

    /** @var array<int, string> */
    private array $taskTitlePool = [];

    /** @var array<int, string> */
    private array $namePool = [];

    /** @var array<int, string> */
    private array $companyPool = [];

    /** @var array<int, string> */
    private array $jobTitlePool = [];

    /** @var array<int, string> */
    private array $addressPool = [];

    /** @var array<int, string> */
    private array $timezones = [
        'UTC', 'America/New_York', 'Europe/London', 'Asia/Tokyo',
        'America/Los_Angeles', 'Europe/Berlin', 'Australia/Sydney',
        'Asia/Kolachi', 'America/Chicago', 'Pacific/Auckland',
    ];

    /** Weighted: 40% pending, 25% in-progress, 10% under-review, 20% completed, 5% cancelled */
    private array $statusWeights = [1, 1, 1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 2, 3, 3, 4, 4, 4, 4, 5];

    /** Weighted activity descriptions matching real app events */
    private array $activityDescriptions = [
        'created_project', 'created_project',
        'updated_project', 'updated_project', 'updated_project', 'updated_project',
        'created_task', 'created_task', 'created_task', 'created_task', 'created_task',
        'updated_task', 'updated_task', 'updated_task', 'updated_task',
        'deleted_task', 'deleted_task', 'deleted_task',
        'deleted_task',
        'deleted_project',
    ];

    public function run(): void
    {
        $startTime = microtime(true);

        $this->hashedPassword = Hash::make('Berry@999');
        $this->nowTs = time();
        $this->nowString = date('Y-m-d H:i:s', $this->nowTs);

        DB::disableQueryLog();

        $this->command->info('Generating faker data pools...');
        $this->generateDataPools();

        $this->command->info('Truncating tables...');
        $this->truncateTables();

        $this->seedLookupTables();
        $this->seedUserBatches();

        Schema::enableForeignKeyConstraints();

        $elapsed = round(microtime(true) - $startTime, 1);
        $this->printSummary($elapsed);
    }

    // ── Data Pools ──────────────────────────────────────────

    private function generateDataPools(): void
    {
        $faker = fake();

        for ($i = 0; $i < 500; $i++) {
            $this->sentencePool[] = $faker->sentence(rand(3, 8));
        }

        for ($i = 0; $i < 200; $i++) {
            $this->paragraphPool[] = $faker->text(250);
        }

        for ($i = 0; $i < 200; $i++) {
            $this->taskTitlePool[] = Str::limit($faker->sentence(rand(2, 6)), 55, '');
        }

        for ($i = 0; $i < 300; $i++) {
            $this->namePool[] = $faker->name();
        }

        for ($i = 0; $i < 100; $i++) {
            $this->companyPool[] = $faker->company();
            $this->jobTitlePool[] = $faker->jobTitle();
            $this->addressPool[] = $faker->address();
        }
    }

    // ── Truncation ──────────────────────────────────────────

    private function truncateTables(): void
    {
        Schema::disableForeignKeyConstraints();

        $tables = [
            'notifications', 'message_user', 'meetings', 'messages',
            'conversations', 'activities', 'task_user', 'tasks',
            'project_members', 'projects', 'user_infos', 'users',
            'statuses', 'stages',
        ];

        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }
    }

    // ── Lookup Tables ───────────────────────────────────────

    private function seedLookupTables(): void
    {
        $this->command->info('Seeding stages (7)...');

        $stages = ['Planning', 'Design', 'Develop', 'Testing', 'Deliver', 'Completed', 'Postponed'];
        $stageRows = [];

        foreach ($stages as $name) {
            $stageRows[] = [
                'user_id' => null,
                'name' => $name,
                'created_at' => $this->nowString,
                'updated_at' => $this->nowString,
            ];
        }

        DB::table('stages')->insert($stageRows);

        $this->command->info('Seeding statuses (5)...');

        $statuses = [
            ['label' => 'Not Started', 'color' => '#CCCCCC'],
            ['label' => 'Started', 'color' => '#FFD700'],
            ['label' => 'In Progress', 'color' => '#0000FF'],
            ['label' => 'Completed', 'color' => '#00FF00'],
            ['label' => 'Cancelled', 'color' => '#FF0000'],
        ];
        $statusRows = [];

        foreach ($statuses as $s) {
            $statusRows[] = [
                'user_id' => null,
                'label' => $s['label'],
                'color' => $s['color'],
                'created_at' => $this->nowString,
                'updated_at' => $this->nowString,
            ];
        }

        DB::table('statuses')->insert($statusRows);
    }

    // ── Main Batch Loop ─────────────────────────────────────

    private function seedUserBatches(): void
    {
        $totalProjects = self::USERS * self::PROJECTS_PER_USER;
        $totalTasks = $totalProjects * self::TASKS_PER_PROJECT;

        $this->command->info(sprintf(
            'Target: %s users, %s projects, %s tasks (~%s total rows)',
            number_format(self::USERS),
            number_format($totalProjects),
            number_format($totalTasks),
            number_format($this->estimateTotalRows()),
        ));

        $projectId = 0;
        $taskId = 0;
        $messageId = 0;
        $batches = (int) ceil(self::USERS / self::USER_BATCH_SIZE);

        $encPasswords = [];
        $encUrls = [];

        for ($e = 0; $e < 10; $e++) {
            $encPasswords[] = Crypt::encryptString(Str::random(7));
            $encUrls[] = Crypt::encryptString('https://zoom.us/j/'.rand(1000000000, 9999999999));
        }

        for ($batch = 0; $batch < $batches; $batch++) {
            $userStart = $batch * self::USER_BATCH_SIZE + 1;
            $userEnd = min(($batch + 1) * self::USER_BATCH_SIZE, self::USERS);

            $this->command->info(sprintf(
                'Batch %d/%d — Users %d–%d',
                $batch + 1,
                $batches,
                $userStart,
                $userEnd,
            ));

            $userNotifierMap = $this->seedUsers($userStart, $userEnd);
            $this->seedUserInfos($userStart, $userEnd);

            $batchProjectStart = $projectId + 1;
            [$projectId, $projectUserMap, $projectMetadataMap] = $this->seedProjects($userStart, $userEnd, $projectId);
            $batchProjectEnd = $projectId;

            $this->seedProjectMembers($batchProjectStart, $batchProjectEnd, $projectUserMap);

            $batchTaskStart = $taskId + 1;
            $taskId = $this->seedTasks($batchProjectStart, $batchProjectEnd, $projectUserMap, $taskId);
            $batchTaskEnd = $taskId;

            $this->seedTaskUser($batchTaskStart, $batchTaskEnd);
            $this->seedActivities($batchProjectStart, $batchProjectEnd, $projectUserMap, $batchTaskStart, $batchTaskEnd);
            $this->seedConversations($batchProjectStart, $batchProjectEnd, $projectUserMap);

            $batchMessageStart = $messageId + 1;
            $messageId = $this->seedMessages($batchProjectStart, $batchProjectEnd, $messageId);
            $batchMessageEnd = $messageId;

            $this->seedMessageUser($batchMessageStart, $batchMessageEnd);
            $this->seedMeetings($batchProjectStart, $batchProjectEnd, $projectUserMap, $encPasswords, $encUrls);
            $this->seedNotifications($userStart, $userEnd, $batchProjectStart, $batchProjectEnd, $projectUserMap, $projectMetadataMap, $userNotifierMap);

            unset($projectUserMap, $projectMetadataMap, $userNotifierMap);
            gc_collect_cycles();
        }
    }

    // ── Per-table Seeders ───────────────────────────────────

    /**
     * @return array<int, array{uuid:string, name:string, username:string, avatar_path:string, email:string}>
     */
    private function seedUsers(int $start, int $end): array
    {
        $rows = [];
        $userNotifierMap = [];

        for ($i = $start; $i <= $end; $i++) {
            $uuid = (string) Str::uuid();
            $name = $this->namePool[array_rand($this->namePool)];
            $username = 'perfuser'.$i;
            $avatarPath = 'https://eu.ui-avatars.com/api/?name='.urlencode($name);
            $email = "perfuser{$i}@perf.test";

            $rows[] = [
                'uuid' => $uuid,
                'name' => $name,
                'username' => $username,
                'avatar_path' => $avatarPath,
                'email' => $email,
                'email_verified_at' => $this->nowString,
                'password' => $this->hashedPassword,
                'remember_token' => Str::random(10),
                'timezone' => $this->timezones[array_rand($this->timezones)],
                'created_at' => $this->randomDate(30, 365),
                'updated_at' => $this->nowString,
            ];

            $userNotifierMap[$i] = [
                'uuid' => $uuid,
                'name' => $name,
                'username' => $username,
                'avatar_path' => $avatarPath,
                'email' => $email,
            ];
        }

        $this->insertChunked('users', $rows);

        return $userNotifierMap;
    }

    private function seedUserInfos(int $start, int $end): void
    {
        $rows = [];

        for ($i = $start; $i <= $end; $i++) {
            $createdAt = $this->historicalCreatedAt();

            $rows[] = [
                'user_id' => $i,
                'mobile' => rand(1000000000, 9999999999),
                'company' => $this->companyPool[array_rand($this->companyPool)],
                'position' => $this->jobTitlePool[array_rand($this->jobTitlePool)],
                'address' => $this->addressPool[array_rand($this->addressPool)],
                'bio' => $this->paragraphPool[array_rand($this->paragraphPool)],
                'created_at' => $createdAt,
                'updated_at' => $this->nowString,
            ];
        }

        $this->insertChunked('user_infos', $rows);
    }

    /**
     * @return array{0: int, 1: array<int, int>, 2: array<int, array{name:string, slug:string}>}
     */
    private function seedProjects(int $userStart, int $userEnd, int $projectId): array
    {
        $rows = [];
        $projectUserMap = [];
        $projectMetadataMap = [];

        for ($userId = $userStart; $userId <= $userEnd; $userId++) {
            for ($p = 0; $p < self::PROJECTS_PER_USER; $p++) {
                $projectId++;
                $name = Str::limit($this->sentencePool[array_rand($this->sentencePool)], 145, '');
                $idSuffix = '-'.$projectId;
                $slug = Str::limit(Str::slug($name), 150 - mb_strlen($idSuffix), '').$idSuffix;
                $createdAt = $this->randomDate(1, 300);

                $rows[] = [
                    'name' => $name,
                    'slug' => $slug,
                    'about' => $this->paragraphPool[array_rand($this->paragraphPool)],
                    'user_id' => $userId,
                    'stage_id' => rand(1, 7),
                    'notes' => rand(0, 3) === 0 ? $this->paragraphPool[array_rand($this->paragraphPool)] : null,
                    'postponed_reason' => null,
                    'stage_updated_at' => $this->randomDate(0, 30),
                    'health_score' => round(rand(0, 10000) / 100, 2),
                    'health_score_calculated_at' => $this->randomDate(0, 3),
                    'created_at' => $createdAt,
                    'updated_at' => $this->nowString,
                ];

                $projectUserMap[$projectId] = $userId;
                $projectMetadataMap[$projectId] = [
                    'name' => $name,
                    'slug' => $slug,
                ];
            }
        }

        $this->insertChunked('projects', $rows);

        return [$projectId, $projectUserMap, $projectMetadataMap];
    }

    /**
     * @param  array<int, int>  $projectUserMap
     */
    private function seedProjectMembers(int $projectStart, int $projectEnd, array $projectUserMap): void
    {
        $rows = [];

        for ($projId = $projectStart; $projId <= $projectEnd; $projId++) {
            $ownerId = $projectUserMap[$projId];
            $memberCount = rand(1, self::MEMBERS_PER_PROJECT_MAX);
            $assigned = [];

            for ($m = 0; $m < $memberCount; $m++) {
                $createdAt = $this->historicalCreatedAt();
                $memberId = rand(1, self::USERS);
                $attempts = 0;

                while (($memberId === $ownerId || isset($assigned[$memberId])) && $attempts < 10) {
                    $memberId = rand(1, self::USERS);
                    $attempts++;
                }

                if ($memberId === $ownerId || isset($assigned[$memberId])) {
                    continue;
                }

                $assigned[$memberId] = true;

                $rows[] = [
                    'project_id' => $projId,
                    'user_id' => $memberId,
                    'active' => (bool) rand(0, 1),
                    'created_at' => $createdAt,
                    'updated_at' => $this->nowString,
                ];
            }

            if (count($rows) >= self::CHUNK_SIZE) {
                $this->insertChunked('project_members', $rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            $this->insertChunked('project_members', $rows);
        }
    }

    /**
     * @param  array<int, int>  $projectUserMap
     */
    private function seedTasks(int $projectStart, int $projectEnd, array $projectUserMap, int $taskId): int
    {
        $rows = [];

        for ($projId = $projectStart; $projId <= $projectEnd; $projId++) {
            $ownerId = $projectUserMap[$projId];

            for ($t = 0; $t < self::TASKS_PER_PROJECT; $t++) {
                $taskId++;
                $statusId = $this->statusWeights[array_rand($this->statusWeights)];
                $isSoftDeleted = rand(1, 10) === 1;
                $hasDueDate = rand(1, 10) <= 3;

                $dueAt = null;
                if ($hasDueDate) {
                    $dueAt = $statusId === 4
                        ? date('Y-m-d H:i:s', $this->nowTs - rand(86400, 86400 * 60))
                        : date('Y-m-d H:i:s', $this->nowTs + rand(-86400 * 14, 86400 * 30));
                }

                $createdAt = $this->randomDate(1, 200);

                $rows[] = [
                    'user_id' => $ownerId,
                    'project_id' => $projId,
                    'status_id' => $statusId,
                    'title' => $this->taskTitlePool[array_rand($this->taskTitlePool)],
                    'description' => rand(0, 2) > 0
                        ? Str::limit($this->paragraphPool[array_rand($this->paragraphPool)], 250, '')
                        : null,
                    'due_at' => $dueAt,
                    'notified' => null,
                    'notify_sent' => false,
                    'deleted_at' => $isSoftDeleted ? $this->randomDate(1, 30) : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];

                if (count($rows) >= self::CHUNK_SIZE) {
                    $this->insertChunked('tasks', $rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            $this->insertChunked('tasks', $rows);
        }

        return $taskId;
    }

    private function seedTaskUser(int $taskStart, int $taskEnd): void
    {
        $rows = [];

        for ($taskId = $taskStart; $taskId <= $taskEnd; $taskId++) {
            if (rand(1, 100) > 40) {
                continue;
            }

            $createdAt = $this->historicalCreatedAt();

            $rows[] = [
                'task_id' => $taskId,
                'user_id' => rand(1, self::USERS),
                'created_at' => $createdAt,
                'updated_at' => $this->nowString,
            ];

            if (count($rows) >= self::CHUNK_SIZE) {
                $this->insertChunked('task_user', $rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            $this->insertChunked('task_user', $rows);
        }
    }

    /**
     * @param  array<int, int>  $projectUserMap
     */
    private function seedActivities(
        int $projectStart,
        int $projectEnd,
        array $projectUserMap,
        int $taskStart,
        int $taskEnd,
    ): void {
        $rows = [];

        for ($projId = $projectStart; $projId <= $projectEnd; $projId++) {
            $ownerId = $projectUserMap[$projId];

            for ($a = 0; $a < self::ACTIVITIES_PER_PROJECT; $a++) {
                $description = $this->activityDescriptions[array_rand($this->activityDescriptions)];
                $isTask = str_contains($description, 'task');
                $createdAt = $this->randomDate(0, 90);

                $rows[] = [
                    'user_id' => $ownerId,
                    'project_id' => $projId,
                    'subject_type' => $isTask ? 'App\\Models\\Task' : 'App\\Models\\Project',
                    'subject_id' => $isTask ? rand($taskStart, max($taskStart, $taskEnd)) : $projId,
                    'is_hidden' => false,
                    'changes' => null,
                    'description' => $description,
                    'affected_users' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];

                if (count($rows) >= self::CHUNK_SIZE) {
                    $this->insertChunked('activities', $rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            $this->insertChunked('activities', $rows);
        }
    }

    /**
     * @param  array<int, int>  $projectUserMap
     */
    private function seedConversations(int $projectStart, int $projectEnd, array $projectUserMap): void
    {
        $rows = [];

        for ($projId = $projectStart; $projId <= $projectEnd; $projId++) {
            $ownerId = $projectUserMap[$projId];

            for ($c = 0; $c < self::CONVERSATIONS_PER_PROJECT; $c++) {
                $createdAt = $this->randomDate(0, 90);

                $rows[] = [
                    'message' => $this->sentencePool[array_rand($this->sentencePool)],
                    'file' => null,
                    'user_id' => $ownerId,
                    'project_id' => $projId,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];

                if (count($rows) >= self::CHUNK_SIZE) {
                    $this->insertChunked('conversations', $rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            $this->insertChunked('conversations', $rows);
        }
    }

    private function seedMessages(int $projectStart, int $projectEnd, int $messageId): int
    {
        $rows = [];

        for ($projId = $projectStart; $projId <= $projectEnd; $projId++) {
            for ($m = 0; $m < self::MESSAGES_PER_PROJECT; $m++) {
                $messageId++;
                $createdAt = $this->randomDate(0, 60);

                $rows[] = [
                    'project_id' => $projId,
                    'message' => $this->paragraphPool[array_rand($this->paragraphPool)],
                    'type' => rand(0, 4) === 0 ? 'sms' : 'mail',
                    'subject' => Str::limit($this->sentencePool[array_rand($this->sentencePool)], 250, ''),
                    'delivered' => (bool) rand(0, 1),
                    'delivered_at' => rand(0, 1) === 1 ? $this->randomDate(0, 30) : null,
                    'batch_id' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];

                if (count($rows) >= self::CHUNK_SIZE) {
                    $this->insertChunked('messages', $rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            $this->insertChunked('messages', $rows);
        }

        return $messageId;
    }

    private function seedMessageUser(int $messageStart, int $messageEnd): void
    {
        $rows = [];

        for ($msgId = $messageStart; $msgId <= $messageEnd; $msgId++) {
            $recipientCount = rand(1, 4);
            $assigned = [];

            for ($r = 0; $r < $recipientCount; $r++) {
                $uid = rand(1, self::USERS);

                if (isset($assigned[$uid])) {
                    continue;
                }

                $assigned[$uid] = true;

                $rows[] = [
                    'message_id' => $msgId,
                    'user_id' => $uid,
                ];
            }

            if (count($rows) >= self::CHUNK_SIZE) {
                $this->insertChunked('message_user', $rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            $this->insertChunked('message_user', $rows);
        }
    }

    /**
     * @param  array<int, int>  $projectUserMap
     * @param  array<int, string>  $encPasswords
     * @param  array<int, string>  $encUrls
     */
    private function seedMeetings(
        int $projectStart,
        int $projectEnd,
        array $projectUserMap,
        array $encPasswords,
        array $encUrls,
    ): void {
        $rows = [];
        $durations = [15, 30, 45, 60];
        $statuses = ['waiting', 'started', 'ended'];

        for ($projId = $projectStart; $projId <= $projectEnd; $projId++) {
            $ownerId = $projectUserMap[$projId];

            for ($mt = 0; $mt < self::MEETINGS_PER_PROJECT; $mt++) {
                $createdAt = $this->randomDate(0, 60);

                $rows[] = [
                    'user_id' => $ownerId,
                    'project_id' => $projId,
                    'meeting_id' => rand(1000000000, 9999999999),
                    'topic' => Str::limit($this->sentencePool[array_rand($this->sentencePool)], 250, ''),
                    'agenda' => $this->sentencePool[array_rand($this->sentencePool)],
                    'duration' => $durations[array_rand($durations)],
                    'password' => $encPasswords[array_rand($encPasswords)],
                    'join_url' => $encUrls[array_rand($encUrls)],
                    'start_url' => $encUrls[array_rand($encUrls)],
                    'start_time' => date('Y-m-d H:i:s', $this->nowTs + rand(-86400 * 30, 86400 * 30)),
                    'status' => $statuses[array_rand($statuses)],
                    'join_before_host' => (bool) rand(0, 1),
                    'timezone' => $this->timezones[array_rand($this->timezones)],
                    'is_programmatic_update' => false,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];

                if (count($rows) >= self::CHUNK_SIZE) {
                    $this->insertChunked('meetings', $rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            $this->insertChunked('meetings', $rows);
        }
    }

    /**
     * @param  array<int, int>  $projectUserMap
     * @param  array<int, array{name:string, slug:string}>  $projectMetadataMap
     * @param  array<int, array{uuid:string, name:string, username:string, avatar_path:string, email:string}>  $userNotifierMap
     */
    private function seedNotifications(
        int $userStart,
        int $userEnd,
        int $projectStart,
        int $projectEnd,
        array $projectUserMap,
        array $projectMetadataMap,
        array $userNotifierMap,
    ): void {
        $rows = [];
        $types = [
            'App\\Notifications\\ProjectInvitation',
            'App\\Notifications\\Task\\TaskDue',
        ];

        for ($userId = $userStart; $userId <= $userEnd; $userId++) {
            for ($n = 0; $n < self::NOTIFICATIONS_PER_USER; $n++) {
                $projId = rand($projectStart, $projectEnd);
                $createdAt = $this->randomDate(0, 30);

                $projectMeta = $projectMetadataMap[$projId] ?? null;
                $ownerId = $projectUserMap[$projId] ?? null;
                $notifierData = $ownerId !== null ? ($userNotifierMap[$ownerId] ?? null) : null;

                if ($projectMeta === null || $notifierData === null) {
                    continue;
                }

                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'type' => $types[array_rand($types)],
                    'notifiable_type' => 'App\\Models\\User',
                    'notifiable_id' => (string) $userId,
                    'data' => json_encode([
                        'message' => 'Sent you a project '.$projectMeta['name'].' invitation',
                        'notifier' => $notifierData,
                        'link' => '/api/v1/projects/'.$projectMeta['slug'],
                    ]),
                    'read_at' => rand(0, 2) === 0 ? $this->randomDate(0, 7) : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                    'signature' => Str::random(64),
                ];
            }

            if (count($rows) >= self::CHUNK_SIZE) {
                $this->insertChunked('notifications', $rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            $this->insertChunked('notifications', $rows);
        }
    }

    // ── Helpers ─────────────────────────────────────────────

    private function randomDate(int $minDaysAgo, int $maxDaysAgo): string
    {
        return fake()
            ->dateTimeBetween("-{$maxDaysAgo} days", "-{$minDaysAgo} days")
            ->format('Y-m-d H:i:s');
    }

    private function historicalCreatedAt(): string
    {
        return fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d H:i:s');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function insertChunked(string $table, array $rows): void
    {
        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function estimateTotalRows(): int
    {
        $projects = self::USERS * self::PROJECTS_PER_USER;
        $tasks = $projects * self::TASKS_PER_PROJECT;

        return 7 + 5
            + self::USERS + self::USERS
            + $projects
            + (int) ($projects * 3)
            + $tasks
            + (int) ($tasks * 0.4)
            + $projects * self::ACTIVITIES_PER_PROJECT
            + $projects * self::CONVERSATIONS_PER_PROJECT
            + $projects * self::MESSAGES_PER_PROJECT
            + (int) ($projects * self::MESSAGES_PER_PROJECT * 2.5)
            + $projects * self::MEETINGS_PER_PROJECT
            + self::USERS * self::NOTIFICATIONS_PER_USER;
    }

    private function printSummary(float $elapsed): void
    {
        $projects = self::USERS * self::PROJECTS_PER_USER;
        $tasks = $projects * self::TASKS_PER_PROJECT;

        $this->command->newLine();
        $this->command->info('=== Performance Seeding Complete ===');
        $this->command->table(
            ['Table', 'Approximate Rows'],
            [
                ['stages', '7'],
                ['statuses', '5'],
                ['users', number_format(self::USERS)],
                ['user_infos', number_format(self::USERS)],
                ['projects', number_format($projects)],
                ['project_members', '~'.number_format($projects * 3)],
                ['tasks', number_format($tasks)],
                ['task_user', '~'.number_format((int) ($tasks * 0.4))],
                ['activities', number_format($projects * self::ACTIVITIES_PER_PROJECT)],
                ['conversations', number_format($projects * self::CONVERSATIONS_PER_PROJECT)],
                ['messages', number_format($projects * self::MESSAGES_PER_PROJECT)],
                ['message_user', '~'.number_format((int) ($projects * self::MESSAGES_PER_PROJECT * 2.5))],
                ['meetings', number_format($projects * self::MEETINGS_PER_PROJECT)],
                ['notifications', number_format(self::USERS * self::NOTIFICATIONS_PER_USER)],
                ['TOTAL', '~'.number_format($this->estimateTotalRows())],
            ],
        );
        $this->command->info("Elapsed: {$elapsed}s");
    }
}
