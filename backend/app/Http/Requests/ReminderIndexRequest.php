<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ReminderIndexRequest extends PaginationRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => [
                'sometimes',
                Rule::in(['upcoming', 'overdue', 'completed', 'all']),
            ],
        ];
    }
}
