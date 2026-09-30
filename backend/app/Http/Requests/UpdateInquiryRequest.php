<?php

namespace App\Http\Requests;

use App\Support\InquiryOptions;
use Illuminate\Validation\Rule;

class UpdateInquiryRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->normalizeEmail();
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email:rfc',
                'max:255',
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
                'regex:'.InquiryOptions::PHONE_PATTERN,
            ],
            'company' => ['sometimes', 'nullable', 'string', 'max:150'],
            'subject' => ['sometimes', 'required', 'string', 'max:150'],
            'source' => [
                'sometimes',
                'required',
                Rule::in(InquiryOptions::SOURCES),
            ],
            'priority' => [
                'sometimes',
                'required',
                Rule::in(InquiryOptions::PRIORITIES),
            ],
            'utm_source' => ['sometimes', 'nullable', 'string', 'max:150'],
            'utm_medium' => ['sometimes', 'nullable', 'string', 'max:150'],
            'utm_campaign' => ['sometimes', 'nullable', 'string', 'max:150'],
            'message' => ['prohibited'],
            'reference_no' => ['prohibited'],
            'assigned_to' => ['prohibited'],
            'status' => ['prohibited'],
            'closed_at' => ['prohibited'],
            'ip_address' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'status.prohibited' => 'Use the status endpoint to change the status.',
            'assigned_to.prohibited' => 'Use the assignment endpoint to change '
                .'the assigned agent.',
            'message.prohibited' => 'The original message cannot be edited. '
                .'Add a reply to the message history instead.',
        ];
    }
}
