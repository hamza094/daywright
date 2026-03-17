<?php

declare(strict_types=1);

return [
    'free' => [
        'max_owned_projects' => 3,
        'max_active_tasks_per_project' => 10,
        'max_members_per_project' => 3,
        'max_created_meetings' => 1,
        'max_api_tokens' => 1,
    ],

    'pro' => [
        'max_owned_projects' => null,
        'max_active_tasks_per_project' => null,
        'max_members_per_project' => null,
        'max_created_meetings' => null,
        'max_api_tokens' => null,
    ],

    'trial' => [
        'duration_days' => 7,
    ],

    'grace_period' => [
        'behavior' => 'full_access',
    ],

    'downgrade' => [
        'behavior' => 'enforce_free_limits',
    ],
];
