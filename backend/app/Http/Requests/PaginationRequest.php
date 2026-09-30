<?php

namespace App\Http\Requests;

class PaginationRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:'.config('inquiry.pagination.maximum', 100),
            ],
            'q' => ['sometimes', 'nullable', 'string', 'max:150'],
        ];
    }
}
