<?php

namespace App\Http\Requests;

class StoreMessageRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:10000'],
            'sender_type' => ['prohibited'],
            'user_id' => ['prohibited'],
        ];
    }
}
