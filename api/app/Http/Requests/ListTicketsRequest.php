<?php

namespace App\Http\Requests;

use App\DTOs\TicketFilters;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'category_id' => ['nullable', 'integer'],
            'team_id' => ['nullable', 'integer'],
            'assignee_id' => ['nullable', 'integer'],
            'unassigned' => ['nullable', 'boolean'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'overdue' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(TicketFilters::SORTABLE)],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('servicedesk.tickets.max_per_page')],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function toDto(): TicketFilters
    {
        return TicketFilters::fromArray($this->validated());
    }
}
