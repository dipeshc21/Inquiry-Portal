<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->whenHas('email'),
            'role' => $this->whenHas('role'),
            'team_id' => $this->whenHas('team_id'),
            'is_active' => $this->whenHas(
                'is_active',
                fn () => (bool) $this->is_active
            ),
            'last_assigned_at' => $this->whenHas(
                'last_assigned_at',
                fn () => $this->last_assigned_at?->toIso8601String()
            ),
            'team' => new TeamResource($this->whenLoaded('team')),
            'created_at' => $this->whenHas(
                'created_at',
                fn () => $this->created_at?->toIso8601String()
            ),
        ];
    }
}
