<?php

namespace App\Http\Requests;

use App\Enums\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'team_id' => ['sometimes', 'nullable', 'integer', 'exists:teams,id'],
        ];
    }
}
