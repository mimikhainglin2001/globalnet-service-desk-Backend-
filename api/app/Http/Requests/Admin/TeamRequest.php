<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255', Rule::unique('teams', 'name')->ignore($this->route('team'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'member_ids' => ['sometimes', 'array'],
            'member_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->whereIn('role', array_map(fn (Role $r) => $r->value, Role::staff())),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'member_ids.*.exists' => 'Only agents and admins can be team members.',
        ];
    }
}
