<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReminderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inquiry_id' => $this->inquiry_id,
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'inquiry' => $this->whenLoaded('inquiry', fn () => [
                'id' => $this->inquiry->id,
                'reference_no' => $this->inquiry->reference_no,
                'subject' => $this->inquiry->subject,
                'assigned_to' => $this->inquiry->assigned_to,
            ]),
            'title' => $this->title,
            'remind_at' => $this->remind_at?->toIso8601String(),
            'is_completed' => (bool) $this->is_completed,
            'is_overdue' => ! $this->is_completed
                && $this->remind_at?->isPast(),
            'notified_at' => $this->notified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
