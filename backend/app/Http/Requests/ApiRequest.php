<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authentication, role middleware, and resource policies perform
        // authorization. Each concrete request owns its validation rules.
        return true;
    }

    public function messages(): array
    {
        return [
            'required' => 'The :attribute field is required.',
            'string' => 'The :attribute must be text.',
            'email' => 'Enter a valid email address.',
            'integer' => 'The :attribute must be a whole number.',
            'numeric' => 'The :attribute must be a number.',
            'boolean' => 'The :attribute must be true or false.',
            'array' => 'The :attribute must be a list.',
            'in' => 'The selected :attribute is invalid.',
            'exists' => 'The selected :attribute is unavailable.',
            'unique' => 'This :attribute is already in use.',
            'confirmed' => 'The :attribute confirmation does not match.',
            'date' => 'Enter a valid date for :attribute.',
            'date_format' => 'The :attribute must use the format :format.',
            'after' => 'The :attribute must be after :date.',
            'after_or_equal' => 'The :attribute must be on or after :date.',
            'regex' => 'The :attribute format is invalid.',
            'file' => 'Each attachment must be a valid uploaded file.',
            'uploaded' => 'The attachment could not be uploaded.',
            'mimes' => 'Attachments must be PDF, DOC, DOCX, JPG, JPEG, or PNG.',
            'extensions' => 'The attachment filename has an unsupported extension.',
            'distinct' => 'The :attribute contains a duplicate value.',
            'prohibited' => 'The :attribute field must not be supplied.',
            'max.string' => 'The :attribute must not exceed :max characters.',
            'max.numeric' => 'The :attribute must not exceed :max.',
            'max.array' => 'The :attribute must contain at most :max items.',
            'max.file' => 'Each attachment must not exceed 5 MB.',
            'min.string' => 'The :attribute must contain at least :min characters.',
            'min.numeric' => 'The :attribute must be at least :min.',
            'min.array' => 'The :attribute must contain at least :min item.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'name',
            'email' => 'email address',
            'phone' => 'phone number',
            'company' => 'company',
            'subject' => 'subject',
            'message' => 'message',
            'body' => 'message',
            'assigned_to' => 'assigned agent',
            'team_id' => 'team',
            'per_page' => 'page size',
            'date_from' => 'start date',
            'date_to' => 'end date',
            'remind_at' => 'reminder date and time',
            'files' => 'attachments',
            'files.*' => 'attachment',
            'utm_source' => 'UTM source',
            'utm_medium' => 'UTM medium',
            'utm_campaign' => 'UTM campaign',
            'auto_assign' => 'automatic assignment',
        ];
    }

    protected function normalizeEmail(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge([
                'email' => mb_strtolower(trim($this->input('email'))),
            ]);
        }
    }

    protected function normalizeBoolean(string $field): void
    {
        if (! $this->exists($field)) {
            return;
        }

        $value = $this->input($field);

        if (in_array($value, [true, 1, '1', 'true'], true)) {
            $this->merge([$field => true]);
        } elseif (in_array($value, [false, 0, '0', 'false'], true)) {
            $this->merge([$field => false]);
        }

        // Leave invalid values untouched so validation rejects them.
    }
}
