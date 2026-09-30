<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AssignTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assignee_id' => ['present', 'nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function assignee(): ?User
    {
        $id = $this->validated('assignee_id');

        return $id === null ? null : User::find($id);
    }
}
