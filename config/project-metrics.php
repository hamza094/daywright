<?php

declare(strict_types=1);

return [
    'health' => [
        'weights' => [
            'tasks' => 0.3,
            'communication' => 0.15,
            'collaboration' => 0.2,
            'stage' => 0.2,
            'activity' => 0.15,
        ],
        'communication' => [
            // Score caps and scaling for conversation-derived communication health
            'max_score' => 100.0,   // cap the communication score
            'scale' => 15.0,        // multiplier applied to log growth
            'log_base' => 2.0,      // base for logarithmic growth (must be > 1)
        ],
        'tasks' => [
            // Component weights (should sum to 100)
            'weights' => [
                'completion' => 50,
                'overdue' => 30,
                'abandonment' => 20,
            ],
        ],
        'collaboration' => [
            // Component weights (should sum to 100)
            'weights' => [
                'member_base' => 40,      // Base score from team size
                'meeting_activity' => 30,  // Recent meeting activity
                'participation' => 30,     // Member participation rate
            ],
            // Ideal targets for 100% normalization
            'ideal_team_size' => 8,
            'ideal_meetings' => 3,
            // Log base used for softer meeting growth (like communication curve)
            'meeting_log_base' => 2.0,

            // Small team behavior
            'small_team_threshold' => 2,   // if members < threshold, cap the score
            'small_team_cap' => 30.0,      // maximum score for very small teams

        ],
    ],

    'time_periods' => [
        'recent_activity_days' => 7,
        'meeting_lookback_days' => 14,
        'risk_assessment_hours' => 48,
        'task_inactivity_days' => 5,
    ],

    'progress' => [
        'activity_count_for_full' => 75, // number of recent activities that count as 100%
    ],
];
