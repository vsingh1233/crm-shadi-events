<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Validation\Rule;

class StoreApiLeadRequest extends SaveLeadRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('createViaApi', Lead::class) === true;
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'status' => $this->input('status', 'new'),
            'source' => $this->input('source', 'manual'),
        ]);
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'request_id' => ['required', 'uuid'],
            'source' => ['required', Rule::in(['manual', 'whatsapp'])],
            'notes' => ['nullable', 'string', 'max:10000'],
            'owner_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'collaborators' => ['prohibited'],
        ]);
    }
}