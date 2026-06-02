<?php

namespace App\Http\Requests\Api\Admin\Board;

use App\Models\Board\BoardTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBoardTermRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:120'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date'],
            'status' => ['sometimes', 'string', Rule::in([
                BoardTerm::STATUS_DRAFT,
                BoardTerm::STATUS_ACTIVE,
                BoardTerm::STATUS_CLOSED,
            ])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
