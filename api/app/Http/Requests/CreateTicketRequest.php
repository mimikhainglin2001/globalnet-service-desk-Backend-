<?php

namespace App\Http\Requests;

use App\DTOs\CreateTicketData;
use App\Enums\TicketPriority;
use App\Http\Requests\Concerns\ValidatesAttachments;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTicketRequest extends FormRequest
{
    use ValidatesAttachments;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('is_active', true),
            ],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            ...$this->attachmentRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'The selected category is invalid or inactive.',
        ];
    }

    public function toDto(): CreateTicketData
    {
        return CreateTicketData::fromArray($this->validated());
    }

    public function idempotencyKey(): ?string
    {
        $key = $this->header(config('servicedesk.idempotency.header'));

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $key = $this->idempotencyKey();
            $max = config('servicedesk.idempotency.max_key_length');

            if ($key !== null && strlen($key) > $max) {
                $validator->errors()->add('idempotency_key', "The Idempotency-Key header may not exceed {$max} characters.");
            }
        });
    }
}
