<?php

namespace App\Http\Requests\Admin;

use App\Enums\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SlaRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'priority' => [
                $required,
                Rule::enum(TicketPriority::class),
                Rule::unique('sla_rules', 'priority')->ignore($this->route('slaRule')),
            ],
            'response_time_hours' => [$required, 'integer', 'min:1', 'max:8760'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
