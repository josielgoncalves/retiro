<?php

namespace App\Http\Requests\Payment;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('managePayments', $this->route('enrollment'));
    }

    public function rules(): array
    {
        return [
            'amount_cents' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date'],
            'payment_method' => ['required', Rule::in([
                Payment::METHOD_PIX, Payment::METHOD_CASH, Payment::METHOD_TRANSFER, Payment::METHOD_OTHER,
            ])],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'installment_ids' => ['nullable', 'array'],
            'installment_ids.*' => ['integer', 'exists:installments,id'],
        ];
    }
}
