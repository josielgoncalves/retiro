<?php

namespace App\Http\Requests\Couple;

use Illuminate\Foundation\Http\FormRequest;

class StoreCoupleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Couple::class);
    }

    public function rules(): array
    {
        return [
            'partner_one_name' => ['required', 'string', 'max:255'],
            'partner_two_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'secondary_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
