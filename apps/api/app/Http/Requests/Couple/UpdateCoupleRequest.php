<?php

namespace App\Http\Requests\Couple;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCoupleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('couple'));
    }

    public function rules(): array
    {
        return [
            'partner_one_name' => ['sometimes', 'required', 'string', 'max:255'],
            'partner_two_name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:30'],
            'secondary_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
