<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true && (! $this->route('lead') || $this->user()->can('update', $this->route('lead')));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => $this->email ? strtolower(trim($this->email)) : null]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:30', 'regex:/^\+?[0-9 () .-]{6,30}$/'],
            'client_location' => ['nullable', 'string', 'max:255'],
            'wedding_location' => ['nullable', 'string', 'max:255'],
            'wedding_start_date' => ['nullable', 'date_format:Y-m-d'],
            'wedding_end_date' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('wedding_start_date'), 'after_or_equal:wedding_start_date')],
            'temperature' => ['nullable', Rule::in(array_keys(Lead::TEMPERATURES))],
            'status' => ['required', Rule::in(array_keys(Lead::STATUSES))],
            'lost_reason' => ['nullable', 'required_if:status,lost', 'string', 'max:5000'],
            'confirm_duplicate' => ['sometimes', 'accepted'],
        ];
    }
}
