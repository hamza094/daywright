<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Unit\DataTransferObjects\Project;

use App\DataTransferObjects\Project\ProjectUpdateData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProjectUpdateDataTest extends TestCase
{
    #[Test]
    public function it_keeps_only_supported_project_update_fields(): void
    {
        $data = ProjectUpdateData::fromArray([
            'name' => 'Refined Name',
            'notes' => '',
            'ignored' => 'value',
        ]);

        $this->assertSame([
            'name' => 'Refined Name',
            'notes' => '',
        ], $data->toArray());

        $this->assertFalse($data->isEmpty());
    }
}
