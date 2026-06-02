<?php

namespace App\Http\Requests\Api\Admin\Board;

use App\Models\Board\BoardTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBoardTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'status' => ['sometimes', 'string', Rule::in([
                BoardTerm::STATUS_DRAFT,
                BoardTerm::STATUS_ACTIVE,
                BoardTerm::STATUS_CLOSED,
            ])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
