<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Enums\InsightType;
use Override;

final class TeamCollaborationInsightBuilder implements InsightBuilderInterface
{
    private const int EXCELLENT_THRESHOLD = 85;

    private const int GOOD_THRESHOLD = 65;

    private const int WARNING_THRESHOLD = 40;

    private const float LOW_PARTICIPATION_THRESHOLD = 0.3; // 30% participation rate

    /**
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    #[Override]
    public function build(mixed $input, array $context = []): array
    {
        if ($input === null || ! is_numeric($input)) {
            return [
                'type' => InsightType::INFO->value,
                'title' => 'No Collaboration Data',
                'message' => 'No collaboration data available.',
                'data' => ['value' => null],
            ];
        }

        $score = (float) max(0, min(100, $input));
        $details = $context['details'] ?? [];
        $memberCount = (int) ($details['member_count'] ?? 0);
        $meetingCount = (int) ($details['meeting_count'] ?? 0);
        $participantCount = (int) ($details['participant_count'] ?? 0);

        // Calculate participation rate once and reuse
        $participationRate = $this->calculateParticipationRate($memberCount, $participantCount);

        return [
            'type' => $this->determineInsightType($score, $participationRate),
            'title' => $this->generateTitle($score, $participationRate),
            'message' => $this->generateMessage($score, $memberCount, $meetingCount, $participationRate, $participantCount),
            'data' => ['value' => $score],
        ];
    }

    private function calculateParticipationRate(int $memberCount, int $participantCount): float
    {
        return $memberCount > 0 ? $participantCount / $memberCount : 0.0;
    }

    private function determineInsightType(float $score, float $participationRate): string
    {
        return match (true) {
            $participationRate < self::LOW_PARTICIPATION_THRESHOLD => InsightType::CRITICAL->value,
            $score >= self::EXCELLENT_THRESHOLD => InsightType::SUCCESS->value,
            $score >= self::GOOD_THRESHOLD => InsightType::INFO->value,
            $score >= self::WARNING_THRESHOLD => InsightType::WARNING->value,
            default => InsightType::WARNING->value,
        };
    }

    private function generateTitle(float $score, float $participationRate): string
    {
        if ($participationRate < self::LOW_PARTICIPATION_THRESHOLD) {
            return 'Low Team Participation';
        }

        return match (true) {
            $score >= self::EXCELLENT_THRESHOLD => 'Excellent Team Collaboration',
            $score >= self::GOOD_THRESHOLD => 'Good Team Collaboration',
            $score >= self::WARNING_THRESHOLD => 'Limited Team Collaboration',
            default => 'Poor Team Collaboration',
        };
    }

    private function generateMessage(float $score, int $memberCount, int $meetingCount, float $participationRate, int $participantCount): string
    {
        $participationPercent = (int) ($participationRate * 100);
        $lookbackDays = $this->getMeetingLookbackDays();
        $idealMeetings = $this->getIdealMeetings();
        $participationDays = $this->getParticipationLookbackDays();

        $participationDaysLabel = $this->pluralize($participationDays, 'day', 'days');
        $meetingDaysLabel = $this->pluralize($lookbackDays, 'day', 'days');
        $meetingsWord = $this->pluralize($meetingCount, 'meeting', 'meetings');

        if ($participationRate < self::LOW_PARTICIPATION_THRESHOLD) {
            return sprintf(
                'Critical: Only %d%% participation (%d of %d) in last %d %s. Meetings: %d %s in last %d %s (ideal %d).',
                $participationPercent, $participantCount, $memberCount, $participationDays, $participationDaysLabel, $meetingCount, $meetingsWord, $lookbackDays, $meetingDaysLabel, $idealMeetings
            );
        }

        return sprintf(
            'Collaboration score: %.1f%%. Participation: %d%% (%d/%d) over last %d %s. Meetings: %d %s in last %d %s (ideal %d).',
            $score, $participationPercent, $participantCount, $memberCount, $participationDays, $participationDaysLabel, $meetingCount, $meetingsWord, $lookbackDays, $meetingDaysLabel, $idealMeetings
        );
    }

    private function getMeetingLookbackDays(): int
    {
        // Prefer insights config (repository uses the same precedence), fallback to project-metrics, then 14
        $insights = config('insights.time_periods.meeting_lookback_days');
        if ($insights !== null) {
            return (int) $insights;
        }

        return (int) config('project-metrics.time_periods.meeting_lookback_days', 14);
    }

    private function getIdealMeetings(): int
    {
        return (int) config('project-metrics.health.collaboration.ideal_meetings', 3);
    }

    private function getParticipationLookbackDays(): int
    {
        $insights = config('insights.time_periods.collaboration_activity_days');
        if ($insights !== null) {
            return (int) $insights;
        }

        return (int) config('project-metrics.time_periods.collaboration_activity_days', 30);
    }

    private function pluralize(int $count, string $singular, string $plural): string
    {
        return $count === 1 ? $singular : $plural;
    }
}
