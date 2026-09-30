<?php

namespace App\Http\Requests;

use App\Enums\CommentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'type' => ['sometimes', Rule::enum(CommentType::class)],
        ];
    }

    public function type(): CommentType
    {
        return CommentType::tryFrom((string) $this->validated('type')) ?? CommentType::PUBLIC;
    }
}
