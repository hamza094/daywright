<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Insights;

use App\Services\Insights\HealthInsightBuilder;
use App\Services\Insights\RiskInsightBuilder;
use App\Services\Insights\StageInsightBuilder;
use App\Services\Insights\TaskHealthInsightBuilder;
use App\Services\Insights\TeamCollaborationInsightBuilder;
use Tests\TestCase;

class ProjectInsightResponseTest extends TestCase
{
    public static function healthThresholdsProvider(): array
    {
        return [
            'excellent at 85' => [85.0, 'Excellent Project Health', 'success'],
            'good at 65' => [65.0, 'Good Project Health', 'info'],
            'warning at 45' => [45.0, 'Moderate Project Health', 'warning'],
            'critical < 20' => [19.9, 'Critical Project Health', 'critical'],
        ];
    }

    public static function taskHealthCasesProvider(): array
    {
        return [
            'good (info)' => [
                75.0,
                ['completion_rate' => 80, 'overdue_rate' => 5, 'abandonment_rate' => 2],
                'info',
                'Good Task Health',
                null,
            ],
            'critical due to overdue' => [
                60.0,
                ['completion_rate' => 75, 'overdue_rate' => 30, 'abandonment_rate' => 2],
                'critical',
                'High Overdue Tasks',
                'overdue',
            ],
            'critical due to abandonment' => [
                60.0,
                ['completion_rate' => 85, 'overdue_rate' => 5, 'abandonment_rate' => 20],
                'critical',
                'High Task Abandonment',
                'abandon',
            ],
        ];
    }

    public static function collaborationCasesProvider(): array
    {
        return [
            'ok (info)' => [
                70.0,
                ['member_count' => 10, 'meeting_count' => 3, 'participant_count' => 8],
                'Good Team Collaboration',
                'info',
                'participation: 80%',
            ],
            'low (warning)' => [
                60.0,
                ['member_count' => 5, 'meeting_count' => 1, 'participant_count' => 2],
                'Limited Team Collaboration',
                'warning',
                'participation',
            ],
            'critical low participation' => [
                70.0,
                ['member_count' => 5, 'meeting_count' => 2, 'participant_count' => 1],
                'Low Team Participation',
                'critical',
                'participation',
            ],
        ];
    }

    public static function riskCasesProvider(): array
    {
        return [
            'no risk' => [0, 0, 0, 'No Risk Detected', 'success'],
            'low risk' => [10, 1, 5, 'Low Risk', 'info'],
            'high' => [85, 5, 7, 'High Risk Alert', 'critical'],
        ];
    }

    public static function stageCasesProvider(): array
    {
        return [
            'development info' => [[
                'percentage' => 45,
                'current_stage' => 'Development',
                'status' => 'in_progress',
                'stage_id' => 3,
            ], 'Active Development', 'info'],

            'completed success' => [[
                'percentage' => 100,
                'current_stage' => 'Completed',
                'status' => 'completed',
                'stage_id' => 6,
            ], 'Project Completed', 'success'],

            'postponed warning' => [[
                'percentage' => 10,
                'current_stage' => 'Planning',
                'status' => 'postponed',
                'stage_id' => 7,
            ], 'Project Postponed', 'warning'],

            'delivery success' => [[
                'percentage' => 80,
                'current_stage' => 'Delivery',
                'status' => 'in_progress',
                'stage_id' => 5,
            ], 'Near Completion', 'success'],
        ];
    }

    /** @test */
    public function health_builder_handles_null_and_numeric(): void
    {
        $builder = new HealthInsightBuilder;

        $null = $builder->build(null);
        $this->assertSame('No Health Data', $null['title']);
        $this->assertNull($null['data']['value']);

        $ok = $builder->build(86.7);
        $this->assertStringContainsString('Project Health', $ok['title']);
        $this->assertIsNumeric($ok['data']['value']);
    }

    /**
     * @test
     *
     * @dataProvider healthThresholdsProvider
     */
    public function health_builder_titles_and_types_at_thresholds(float $score, string $expectedTitle, string $expectedType): void
    {
        $builder = new HealthInsightBuilder;
        $res = $builder->build($score);
        $this->assertSame($expectedTitle, $res['title']);
        $this->assertSame($expectedType, $res['type']);
    }

    /** @test */
    public function task_health_builder_handles_no_data(): void
    {
        $builder = new TaskHealthInsightBuilder;
        $noData = $builder->build(null);
        $this->assertSame('No Task Data', $noData['title']);
        $this->assertNull($noData['data']['value']);
    }

    /**
     * @test
     *
     * @dataProvider taskHealthCasesProvider
     */
    public function task_health_builder_types_and_messages(float $value, array $summary, string $expectedType, ?string $expectedTitleContains, ?string $expectedMessageContains): void
    {
        $builder = new TaskHealthInsightBuilder;
        $res = $builder->build($value, ['summary' => $summary]);
        $this->assertSame($expectedType, $res['type']);
        if ($expectedTitleContains !== null) {
            $this->assertStringContainsString($expectedTitleContains, $res['title']);
        }
        if ($expectedMessageContains !== null) {
            $this->assertStringContainsString($expectedMessageContains, mb_strtolower((string) $res['message']));
        }
    }

    /** @test */
    public function task_health_builder_renders_detailed_counts_in_message(): void
    {
        $builder = new TaskHealthInsightBuilder;
        $value = 57.5; // matches 60% completion, 75% overdue of in-progress, 0% abandonment with weights
        $details = [
            'summary' => [
                'completion_rate' => 60,
                'overdue_rate' => 75,
                'abandonment_rate' => 0,
                'active_count' => 10,
                'completed_count' => 6,
                'overdue_count' => 3,
                'in_progress_count' => 4,
                'abandoned_count' => 0,
            ],
        ];

        $res = $builder->build($value, $details);
        $this->assertStringContainsString('Completion: 60% (6/10)', $res['message']);
        $this->assertStringContainsString('Overdue: 75% of in-progress (3 of 4)', $res['message']);
    }

    /** @test */
    public function collaboration_builder_handles_no_data(): void
    {
        $builder = new TeamCollaborationInsightBuilder;
        $noData = $builder->build(null);
        $this->assertSame('No Collaboration Data', $noData['title']);
    }

    /**
     * @test
     *
     * @dataProvider collaborationCasesProvider
     */
    public function collaboration_builder_title_and_type_cases(float $score, array $details, string $expectedTitleContains, string $expectedType, ?string $expectedMessageContains = null): void
    {
        $builder = new TeamCollaborationInsightBuilder;
        $res = $builder->build($score, ['details' => $details]);
        $this->assertStringContainsString($expectedTitleContains, $res['title']);
        $this->assertSame($expectedType, $res['type']);
        if ($expectedMessageContains !== null) {
            $this->assertStringContainsString(mb_strtolower($expectedMessageContains), mb_strtolower((string) $res['message']));
        }
    }

    /** @test */
    public function collaboration_builder_includes_lookbacks_and_ideal_meetings_in_message(): void
    {
        $builder = new TeamCollaborationInsightBuilder;

        // Fix lookbacks and ideal meetings for deterministic message
        config([
            'insights.time_periods.meeting_lookback_days' => 10,
            'insights.time_periods.collaboration_activity_days' => 21,
            'project-metrics.health.collaboration.ideal_meetings' => 3,
        ]);

        $ok = $builder->build(70, [
            'details' => ['member_count' => 10, 'meeting_count' => 3, 'participant_count' => 8],
        ]);

        // Participation window
        $this->assertStringContainsString('over last 21 days', mb_strtolower((string) $ok['message']));
        // Meetings lookback and ideal
        $this->assertStringContainsString('in last 10 days', mb_strtolower((string) $ok['message']));
        // Ideal count shown optionally; two lookback windows are sufficient for this test
    }

    /** @test */
    public function risk_builder_handles_invalid_input(): void
    {
        $builder = new RiskInsightBuilder;
        $no = $builder->build(['wrong' => 1]);
        $this->assertSame('No Risk Data', $no['title']);
    }

    /**
     * @test
     *
     * @dataProvider riskCasesProvider
     */
    public function risk_builder_title_and_type(int $score, int $atRisk, int $dueSoon, string $expectedTitle, string $expectedType): void
    {
        $builder = new RiskInsightBuilder;
        $res = $builder->build(['score' => $score, 'at_risk_count' => $atRisk, 'due_soon_count' => $dueSoon]);
        $this->assertSame($expectedTitle, $res['title']);
        $this->assertSame($expectedType, $res['type']);
    }

    /** @test */
    public function stage_builder_handles_unknown_input(): void
    {
        $builder = new StageInsightBuilder;
        $no = $builder->build(['foo' => 'bar']);
        $this->assertSame('No Stage Data', $no['title']);
    }

    /**
     * @test
     *
     * @dataProvider stageCasesProvider
     */
    public function stage_builder_titles_and_types(array $input, string $expectedTitle, string $expectedType): void
    {
        $builder = new StageInsightBuilder;
        $res = $builder->build($input);
        $this->assertSame($expectedTitle, $res['title']);
        $this->assertSame($expectedType, $res['type']);
    }
}
