<?php

namespace App\Http\Requests\Installment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegenerateInstallmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageInstallments', $this->route('enrollment'));
    }

    public function rules(): array
    {
        return [
            'installment_count' => ['required', 'integer', 'min:1', 'max:120'],
            'first_due_date' => ['required', 'date'],
            'periodicity' => ['required', Rule::in(['monthly', 'weekly'])],
            'confirm' => ['sometimes', 'boolean'],
        ];
    }
}
