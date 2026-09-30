<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\InquiryOptions;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->normalizeEmail();
        $this->normalizeBoolean('is_active');
    }

    public function rules(): array
    {
        $target = $this->route('user');
        $targetId = $target instanceof User ? $target->id : $target;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($targetId),
            ],
            'password' => [
                'sometimes',
                'required',
                'string',
                'max:200',
                'confirmed',
                Password::min(12)->letters()->mixedCase()->numbers(),
            ],
            'password_confirmation' => [
                'required_with:password',
                'string',
                'max:200',
            ],
            'role' => [
                'sometimes',
                'required',
                'string',
                Rule::in(InquiryOptions::ROLES),
            ],
            'team_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('teams', 'id'),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'password.min' => 'Use a password with at least 12 characters.',
            'password.letters' => 'The password must contain a letter.',
            'password.mixed' => 'The password must contain uppercase and '
                .'lowercase letters.',
            'password.numbers' => 'The password must contain a number.',
            'password_confirmation.required_with' => 'Confirm the new password.',
        ];
    }
}
