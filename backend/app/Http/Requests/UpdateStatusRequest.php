<?php

namespace App\Http\Requests;

use App\Support\InquiryOptions;
use Illuminate\Validation\Rule;

class UpdateStatusRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(InquiryOptions::STATUSES),
            ],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
