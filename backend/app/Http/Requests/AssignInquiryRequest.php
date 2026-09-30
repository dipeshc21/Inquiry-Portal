<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class AssignInquiryRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'assigned_to' => [
                'present',
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query
                        ->where('role', 'agent')
                        ->where('is_active', true)
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'assigned_to.present' => 'Provide an agent ID, or null to remove '
                .'the assignment.',
            'assigned_to.exists' => 'Select an active agent.',
        ];
    }
}
