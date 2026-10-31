<?php

namespace App\Http\Requests\Installment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInstallmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $installment = $this->route('installment');

        return $installment && $this->user()->can('manageInstallments', $installment->enrollment);
    }

    public function rules(): array
    {
        return [
            'due_date' => ['required', 'date'],
            'amount_cents' => ['sometimes', 'required', 'integer', 'min:1'],
            'compensating_installment_id' => ['required_with:amount_cents', 'nullable', 'integer'],
        ];
    }
}
