<?php

namespace App\Http\Requests\Retreat;

use App\Models\Retreat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreRetreatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Retreat::class);
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
            'cover_image' => [
                'nullable',
                File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024),
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value || @getimagesize($value->getRealPath()) === false) {
                        $fail('O arquivo enviado não contém uma imagem válida.');
                    }
                },
            ],
            'pix_key_type' => ['nullable', Rule::in(['cpf', 'cnpj', 'email', 'phone', 'random'])],
            'pix_key' => ['nullable', 'string', 'max:255', 'required_with:pix_key_type'],
            'pix_beneficiary_name' => ['nullable', 'string', 'max:255'],
            'receipt_whatsapp' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+() .-]+$/'],
        ];
    }
}
