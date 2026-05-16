<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Unit\DataTransferObjects\Project;

use App\DataTransferObjects\Project\ProjectStageUpdateData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProjectStageUpdateDataTest extends TestCase
{
    #[Test]
    public function it_builds_stage_update_data_from_a_payload(): void
    {
        $data = ProjectStageUpdateData::fromArray([
            'stage' => '4',
            'postponed_reason' => 'Vendor dependency',
        ]);

        $this->assertSame([
            'stage' => 4,
            'postponed_reason' => 'Vendor dependency',
        ], $data->toArray());
    }
}
