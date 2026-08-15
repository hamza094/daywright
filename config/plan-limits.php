<?php

declare(strict_types=1);

return [
    'free' => [
        'max_owned_projects' => 3,
        'max_tasks_per_project' => 10,
        'max_members_per_project' => 3,
        'max_created_meetings' => 2,
        'max_api_tokens' => 3,
    ],

    'pro' => [
        'max_owned_projects' => null,
        'max_tasks_per_project' => null,
        'max_members_per_project' => null,
        'max_created_meetings' => null,
        'max_api_tokens' => 5,
    ],

    'trial' => [
        'duration_days' => 7,
    ],

];
