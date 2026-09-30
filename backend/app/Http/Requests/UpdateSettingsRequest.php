<?php

namespace App\Http\Requests;

use App\Support\InquiryOptions;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->normalizeBoolean('auto_assign');
    }

    public function rules(): array
    {
        return [
            'auto_assign' => ['required', 'boolean'],
            'strategy' => [
                'required',
                'string',
                Rule::in(InquiryOptions::ASSIGNMENT_STRATEGIES),
            ],
        ];
    }
}
