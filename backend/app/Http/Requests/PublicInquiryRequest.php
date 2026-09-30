<?php

namespace App\Http\Requests;

use App\Support\InquiryOptions;
use Illuminate\Validation\Rule;

class PublicInquiryRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->normalizeEmail();
    }

    public function rules(): array
    {
        $extensions = implode(
            ',',
            config('inquiry.uploads.extensions')
        );

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'phone' => [
                'nullable',
                'string',
                'max:30',
                'regex:'.InquiryOptions::PHONE_PATTERN,
            ],
            'company' => ['nullable', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'source' => [
                'sometimes',
                'nullable',
                Rule::in(InquiryOptions::SOURCES),
            ],
            'utm_source' => ['nullable', 'string', 'max:150'],
            'utm_medium' => ['nullable', 'string', 'max:150'],
            'utm_campaign' => ['nullable', 'string', 'max:150'],
            'honeypot' => ['nullable', 'string', 'max:0'],
            'files' => [
                'sometimes',
                'array',
                'max:'.config('inquiry.uploads.max_files', 3),
            ],
            'files.*' => [
                'required',
                'file',
                'mimes:'.$extensions,
                'extensions:'.$extensions,
                'max:'.config('inquiry.uploads.max_size_kb', 5120),
            ],
            'status' => ['prohibited'],
            'priority' => ['prohibited'],
            'assigned_to' => ['prohibited'],
            'reference_no' => ['prohibited'],
            'ip_address' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'honeypot.max' => 'The submission could not be accepted.',
            'phone.regex' => 'Enter a valid phone number using digits, spaces, '
                .'parentheses, periods, hyphens, and an optional leading +.',
            'files.max' => 'You can upload at most 3 attachments.',
            'message.min' => 'Please describe your inquiry in at least 10 characters.',
        ];
    }
}
