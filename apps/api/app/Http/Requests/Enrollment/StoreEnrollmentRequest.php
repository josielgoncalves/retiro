<?php

namespace App\Http\Requests\Enrollment;

use App\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Enrollment::class);
    }

    public function rules(): array
    {
        return [
            'retreat_id' => ['required', 'integer', 'exists:retreats,id'],
            'couple_id' => [
                'required',
                'integer',
                'exists:couples,id',
                Rule::unique('enrollments')->where('retreat_id', $this->input('retreat_id')),
            ],
            'payment_mode' => ['required', Rule::in([Enrollment::MODE_FIXED, Enrollment::MODE_FLEXIBLE])],
            'total_amount_cents' => ['required', 'integer', 'min:1'],
            'registered_at' => ['nullable', 'date'],
            'installments' => ['required_if:payment_mode,'.Enrollment::MODE_FIXED, 'array', 'min:1'],
            'installments.*.due_date' => ['required', 'date'],
            'installments.*.amount_cents' => ['sometimes', 'required', 'integer', 'min:1'],
        ];
    }
}
