<?php

namespace App\Http\Requests\Retreat;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRetreatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('retreat'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'required', 'date', 'after_or_equal:start_date'],
            'location' => ['nullable', 'string', 'max:255'],
            'registration_open' => ['boolean'],
            'status' => ['sometimes', 'required', 'in:draft,active,finished,archived'],
        ];
    }
}
