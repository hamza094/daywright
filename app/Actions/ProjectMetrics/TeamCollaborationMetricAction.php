<?php

declare(strict_types=1);

namespace App\Actions\ProjectMetrics;

use App\Models\Project;

final class TeamCollaborationMetricAction
{
    public function execute(Project $project): float
    {
        $activeMembers = max(0, (int) ($project->active_members_count ?? 0));
        $recentMeetings = max(0, (int) ($project->recent_meetings_count ?? 0));
        $recentParticipants = max(0, (int) ($project->recent_participants_count ?? 0));

        if ($activeMembers === 0) {
            return 0.0;
        }

        // ✅ Normalize metrics (0–100 scale)
        $memberRate = $this->normalizeMemberRate($activeMembers);
        $meetingRate = $this->normalizeMeetingRate($recentMeetings);
        $participationRate = $this->normalizeParticipationRate($recentParticipants, $activeMembers);

        // Weights from config (member_base/meeting_activity/participation in 0..100)
        $weights = $this->weights();

        $collaborationHealth =
            ($memberRate * $weights['members']) +
            ($meetingRate * $weights['meetings']) +
            ($participationRate * $weights['participation']);

        $smallTeamThreshold = (int) config('project-metrics.health.collaboration.small_team_threshold', 2);
        $smallTeamCap = (float) config('project-metrics.health.collaboration.small_team_cap', 30.0);
        if ($activeMembers < $smallTeamThreshold) {
            return min($smallTeamCap, round($collaborationHealth, 1));
        }

        return round($this->clampPercent($collaborationHealth), 1);
    }

    private function normalizeMemberRate(int $activeMembers): float
    {
        $idealSize = (int) config('project-metrics.health.collaboration.ideal_team_size', 8);
        if ($idealSize <= 0) {
            return 0.0;
        }

        return $this->clampPercent(($activeMembers / $idealSize) * 100);
    }

    private function normalizeMeetingRate(int $recentMeetings): float
    {
        $idealMeetings = (int) config('project-metrics.health.collaboration.ideal_meetings', 3);
        if ($idealMeetings <= 0) {
            return 0.0;
        }

        // Softer growth using logarithmic curve like communication metric
        $base = (float) config('project-metrics.health.collaboration.meeting_log_base', 2.0);
        $base = $base > 1 ? $base : 2.0;

        $numerator = log(1 + max(0, $recentMeetings), $base);
        $denominator = log(1 + $idealMeetings, $base);
        if ($denominator <= 0) {
            return 0.0;
        }

        $fraction = $numerator / $denominator; // ~1.0 at ideal; <1 before, >1 after

        return $this->clampPercent($fraction * 100);
    }

    private function normalizeParticipationRate(int $participants, int $totalMembers): float
    {
        if ($totalMembers === 0) {
            return 0.0;
        }
        // cap participants to not exceed totalMembers
        $participants = min($participants, $totalMembers);
        $rate = ($participants / $totalMembers) * 100;

        return $this->clampPercent($rate);
    }

    /**
     * Return normalized weight fractions for collaboration metric components.
     *
     * @return array{members: float, meetings: float, participation: float}
     */
    private function weights(): array
    {
        $cfg = (array) config('project-metrics.health.collaboration.weights', []);
        // The config uses percent weights (0..100); normalize to fractions that sum to 1
        $members = (float) ($cfg['member_base'] ?? 20);       // fallback to 20%
        $meetings = (float) ($cfg['meeting_activity'] ?? 30); // fallback to 30%
        $participation = (float) ($cfg['participation'] ?? 50); // fallback to 50%
        $sum = $members + $meetings + $participation;
        if ($sum <= 0) {
            return ['members' => 0.2, 'meetings' => 0.3, 'participation' => 0.5];
        }

        return [
            'members' => $members / $sum,
            'meetings' => $meetings / $sum,
            'participation' => $participation / $sum,
        ];
    }

    private function clampPercent(float $value): float
    {
        if (! is_finite($value) || $value < 0.0) {
            return 0.0;
        }

        if ($value > 100.0) {
            return 100.0;
        }

        return $value;
    }
}
