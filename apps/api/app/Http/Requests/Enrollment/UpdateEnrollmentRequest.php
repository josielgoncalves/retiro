<?php

namespace App\Http\Requests\Enrollment;

use App\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('enrollment'));
    }

    public function rules(): array
    {
        return [
            'payment_mode' => ['sometimes', 'required', Rule::in([Enrollment::MODE_FIXED, Enrollment::MODE_FLEXIBLE])],
            'total_amount_cents' => ['sometimes', 'required', 'integer', 'min:1'],
        ];
    }
}
