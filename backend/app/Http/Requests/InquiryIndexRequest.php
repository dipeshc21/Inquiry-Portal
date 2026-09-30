<?php

namespace App\Http\Requests;

use App\Support\InquiryOptions;
use Illuminate\Validation\Rule;

class InquiryIndexRequest extends PaginationRequest
{
    protected function prepareForValidation(): void
    {
        $this->normalizeBoolean('unassigned');

        foreach (['status', 'source'] as $field) {
            $value = $this->input($field);

            if (is_string($value) && $value !== '') {
                $this->merge([
                    $field => array_values(array_filter(
                        array_map('trim', explode(',', $value)),
                        fn (string $item): bool => $item !== ''
                    )),
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['sometimes', 'array', 'max:6'],
            'status.*' => [
                'required',
                'string',
                'distinct',
                Rule::in(InquiryOptions::STATUSES),
            ],
            'source' => ['sometimes', 'array', 'max:6'],
            'source.*' => [
                'required',
                'string',
                'distinct',
                Rule::in(InquiryOptions::SOURCES),
            ],
            'priority' => [
                'sometimes',
                'nullable',
                Rule::in(InquiryOptions::PRIORITIES),
            ],
            'assigned_to' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'unassigned' => ['sometimes', 'boolean'],
            'date_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'date_to' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
                Rule::when(
                    $this->filled('date_from'),
                    ['after_or_equal:date_from']
                ),
            ],
            'sort_by' => [
                'sometimes',
                Rule::in(InquiryOptions::SORT_COLUMNS),
            ],
            'sort_dir' => [
                'sometimes',
                Rule::in(['asc', 'desc']),
            ],
            'ids' => ['sometimes', 'array', 'min:1', 'max:1000'],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }
}
