<?php

namespace App\Http\Requests\Announcement;

use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Announcement::class);
    }

    public function rules(): array
    {
        return [
            'retreat_id' => ['required', 'integer', 'exists:retreats,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'visible_until' => ['nullable', 'date'],
        ];
    }
}
