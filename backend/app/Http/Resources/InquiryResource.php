<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InquiryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'name' => $this->whenHas('name'),
            'email' => $this->whenHas('email'),
            'phone' => $this->whenHas('phone'),
            'company' => $this->whenHas('company'),
            'subject' => $this->whenHas('subject'),
            'message' => $this->whenHas('message'),
            'source' => $this->whenHas('source'),
            'utm_source' => $this->whenHas('utm_source'),
            'utm_medium' => $this->whenHas('utm_medium'),
            'utm_campaign' => $this->whenHas('utm_campaign'),
            'status' => $this->whenHas('status'),
            'priority' => $this->whenHas('priority'),
            'assigned_to' => $this->whenHas('assigned_to'),
            'assignee' => new UserResource($this->whenLoaded('assignee')),
            'closed_at' => $this->whenHas(
                'closed_at',
                fn () => $this->closed_at?->toIso8601String()
            ),
            'created_at' => $this->whenHas(
                'created_at',
                fn () => $this->created_at?->toIso8601String()
            ),
            'updated_at' => $this->whenHas(
                'updated_at',
                fn () => $this->updated_at?->toIso8601String()
            ),
            'messages_count' => $this->whenCounted('messages'),
            'notes_count' => $this->whenCounted('notes'),
            'attachments_count' => $this->whenCounted('attachments'),
            'incomplete_reminders_count' => $this->whenHas(
                'incomplete_reminders_count'
            ),
            'messages' => MessageResource::collection(
                $this->whenLoaded('messages')
            ),
            'notes' => NoteResource::collection(
                $this->whenLoaded('notes')
            ),
            'reminders' => ReminderResource::collection(
                $this->whenLoaded('reminders')
            ),
            'attachments' => AttachmentResource::collection(
                $this->whenLoaded('attachments')
            ),
            'activity_logs' => ActivityLogResource::collection(
                $this->whenLoaded('activityLogs')
            ),
        ];
    }
}
