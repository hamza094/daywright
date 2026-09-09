<?php

declare(strict_types=1);

namespace App\Documentation\Attributes;

use Attribute;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;

/**
 * Attribute to explicitly document archived resource error responses.
 *
 * Use this only when controller or service behavior rejects an archived
 * resource independently of Laravel's implicit route binding.
 *
 * @see \App\Models\Project::resolveRouteBinding()
 * @see \App\Models\Project::resolveChildRouteBinding()
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class ArchivedResourceErrorResponse extends ScrambleResponse
{
    public function __construct(string $resourceType = 'project')
    {
        $description = $resourceType === 'task'
            ? 'Conflict - Task is archived and cannot be accessed'
            : 'Conflict - Project is archived and cannot be accessed';

        parent::__construct(409, $description);
    }
}
