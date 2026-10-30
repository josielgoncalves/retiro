<?php

namespace App\Http\Requests\Retreat;

use Illuminate\Foundation\Http\FormRequest;

class StoreRetreatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Retreat::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'location' => ['nullable', 'string', 'max:255'],
            'registration_open' => ['boolean'],
            'status' => ['required', 'in:draft,active,finished,archived'],
        ];
    }
}
