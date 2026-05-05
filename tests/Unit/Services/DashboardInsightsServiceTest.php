<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repository\DashboardInsightsRepository;
use App\Services\Dashboard\DashboardInsightsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardInsightsServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_correct_kpis_for_user(): void
    {
        // Arrange
        $mockRepo = $this->createMock(DashboardInsightsRepository::class);
        $mockRepo->method('getUserProjects')->willReturn(collect([(object) ['id' => 1], (object) ['id' => 2]]));
        $mockRepo->method('getOverdueTasksCount')->willReturn(5);
        $mockRepo->method('getCriticalProjectsCount')->willReturn(1);
        $mockRepo->method('getTaskCompletionRate')->willReturn(80.0);

        $service = new DashboardInsightsService($mockRepo);

        // Act
        $kpis = $service->getKPIs(123);

        // Assert
        $this->assertEquals(2, $kpis['total_projects']['value']);
        $this->assertEquals(1, $kpis['critical_projects']['value']);
        $this->assertEquals(5, $kpis['overdue_tasks']['value']);
        $this->assertEquals(80.0, $kpis['completion_rate']['value']);
    }

    /** @test */
    public function it_returns_actionable_insights_for_various_scenarios(): void
    {
        // Arrange
        $mockRepo = $this->createMock(DashboardInsightsRepository::class);
        $mockRepo->method('getUserProjects')->willReturn(collect([(object) ['id' => 1]]));
        $mockRepo->method('getOverdueTasksCount')->willReturn(12); // above critical threshold
        $mockRepo->method('getCriticalProjectsCount')->willReturn(2);

        $service = new DashboardInsightsService($mockRepo);

        // Act
        $insights = $service->getActionableInsights(123);

        // Assert
        $this->assertNotEmpty($insights);

        $this->assertTrue(collect($insights)->contains(fn ($item): bool => $item['type'] === 'critical'));

        $this->assertTrue(collect($insights)->contains(fn ($item): bool => $item['type'] === 'warning'));
    }

    /** @test */
    public function it_returns_no_active_projects_insight(): void
    {
        // Arrange
        $mockRepo = $this->createMock(DashboardInsightsRepository::class);
        $mockRepo->method('getUserProjects')->willReturn(collect([]));
        $mockRepo->method('getOverdueTasksCount')->willReturn(0);
        $mockRepo->method('getCriticalProjectsCount')->willReturn(0);

        $service = new DashboardInsightsService($mockRepo);

        // Act
        $insights = $service->getActionableInsights(123);

        // Assert
        $this->assertNotEmpty($insights);
        $this->assertTrue(collect($insights)->contains(fn ($item): bool => $item['type'] === 'info'));
    }

    /** @test */
    public function it_returns_portfolio_healthy_insight_when_no_issues(): void
    {
        // Arrange
        $mockRepo = $this->createMock(DashboardInsightsRepository::class);
        $mockRepo->method('getUserProjects')->willReturn(collect([(object) ['id' => 1]]));
        $mockRepo->method('getOverdueTasksCount')->willReturn(0);
        $mockRepo->method('getCriticalProjectsCount')->willReturn(0);

        $service = new DashboardInsightsService($mockRepo);

        // Act
        $insights = $service->getActionableInsights(123);

        // Assert
        $this->assertNotEmpty($insights);
        $this->assertTrue(collect($insights)->contains(fn ($item): bool => $item['type'] === 'success'));
    }

    /** @test */
    public function it_returns_warning_insight_for_some_overdue_tasks_below_critical(): void
    {
        // Arrange
        $mockRepo = $this->createMock(DashboardInsightsRepository::class);
        $mockRepo->method('getUserProjects')->willReturn(collect([(object) ['id' => 1]]));
        $mockRepo->method('getOverdueTasksCount')->willReturn(5); // below critical threshold
        $mockRepo->method('getCriticalProjectsCount')->willReturn(0);

        $service = new DashboardInsightsService($mockRepo);

        // Act
        $insights = $service->getActionableInsights(123);

        // Assert
        $this->assertNotEmpty($insights);
        $this->assertTrue(collect($insights)->contains(fn ($item): bool => $item['type'] === 'warning'));
    }
}
