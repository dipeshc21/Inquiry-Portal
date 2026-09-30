<?php

namespace App\Http\Requests;

class StoreNoteRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:5000'],
            'is_internal' => ['sometimes', 'accepted'],
            'user_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'is_internal.accepted' => 'Notes must be internal.',
        ];
    }
}
