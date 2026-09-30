<?php

namespace App\Http\Requests;

class StoreReminderRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'remind_at' => ['required', 'date', 'after:now'],
            'user_id' => ['prohibited'],
            'is_completed' => ['prohibited'],
            'notified_at' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'remind_at.after' => 'Choose a reminder time in the future.',
        ];
    }
}
