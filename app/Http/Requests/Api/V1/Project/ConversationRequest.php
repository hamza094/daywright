<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Project;

use App\DataTransferObjects\Project\CreateConversationData;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Override;

#[SchemaName('ConversationStoreRequestData')]
class ConversationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function toDto(): CreateConversationData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateConversationData::fromArray($validated);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Conversation message body. Required when no file is attached.
             *
             * @example Can someone review the latest copy draft?
             */
            'message' => 'required_without:file|string|min:2|max:1000',

            /**
             * Optional conversation attachment.
             * Accepted types: jpg, png, pdf, docx. Maximum size: 700 KB.
             */
            'file' => 'required_without:message|file|max:700|mimes:jpg,png,pdf,docx',
        ];
    }

    #[Override]
    public function messages(): array
    {
        return [
            'message.required_without' => 'A message is required if no file is uploaded.',
            'file.required_without' => 'A file is required if no message is provided.',
        ];
    }
}
