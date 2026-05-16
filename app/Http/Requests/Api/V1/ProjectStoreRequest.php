<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\DataTransferObjects\Project\ProjectCreateData;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;

#[SchemaName('ProjectStoreRequestData')]
class ProjectStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function projectCreateData(): ProjectCreateData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ProjectCreateData::fromArray($validated);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Project name shown across the dashboard and collaboration views.
             *
             * @example The Dimension
             */
            'name' => 'required|string|max:150|min:4',
            /**
             * Short project overview shown in summaries and invitations.
             *
             * @example This project tracks the launch plan for our new mobile app.
             */
            'about' => 'required|min:15',
            /**
             * Stage identifier selected when the project is created.
             *
             * @example 1
             */
            'stage_id' => 'required|int|between:1,5',
            /**
             * Optional private notes for the project owner.
             *
             * @example Capture launch blockers and external dependencies here.
             */
            'notes' => 'sometimes|max:250',
            /**
             * Optional starter tasks created alongside the project.
             * Only three tasks are allowed during project creation.
             */
            'tasks' => 'sometimes|array|max:3',
            /**
             * Starter task title.
             *
             * @example Draft the launch checklist
             */
            'tasks.*.title' => 'required|string|min:5|max:55',
        ];
    }
}
