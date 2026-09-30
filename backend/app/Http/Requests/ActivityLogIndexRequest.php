<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ActivityLogIndexRequest extends PaginationRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'inquiry_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('inquiries', 'id'),
            ],
            'user_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'action' => ['sometimes', 'nullable', 'string', 'max:80'],
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
        ];
    }
}
