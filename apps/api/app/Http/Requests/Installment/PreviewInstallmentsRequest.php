<?php

namespace App\Http\Requests\Installment;

use App\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewInstallmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Enrollment::class);
    }

    public function rules(): array
    {
        return [
            'total_amount_cents' => ['required', 'integer', 'min:1'],
            'installment_count' => ['required', 'integer', 'min:1', 'max:120'],
            'first_due_date' => ['required', 'date'],
            'periodicity' => ['required', Rule::in(['monthly', 'weekly'])],
        ];
    }
}
