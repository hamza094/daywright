<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Rules\Iso8601Timestamp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

use function Safe\json_decode;

class MessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function validator($factory)
    {
        return $factory->make(
            $this->sanitize(), $this->container->call($this->rules(...)), $this->messages()
        );
    }

    public function sanitize()
    {
        $this->merge([
            'users' => json_decode((string) $this->input('users'), true),
        ]);

        return $this->all();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'message' => 'required|max:200',
            'mail' => 'sometimes',
            'subject' => Rule::requiredIf(request()->mail === true),
            'sms' => 'sometimes',
            'users' => 'present|required',
            'delivered_at' => [
                'sometimes',
                'bail',
                'string',
                new Iso8601Timestamp,
            ],
            'date' => 'prohibited',
            'time' => 'prohibited',
        ];
    }

    #[Override]
    public function messages()
    {
        return [
            'users.required' => "You haven't selected any user",
        ];
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        if (! $this->has('delivered_at')) {
            return;
        }

        $normalizedDeliveredAt = Iso8601Timestamp::normalizeToUtc((string) $this->input('delivered_at'));

        if ($normalizedDeliveredAt === null) {
            return;
        }

        $this->merge([
            'delivered_at' => $normalizedDeliveredAt,
        ]);
    }
}
