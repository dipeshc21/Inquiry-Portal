<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->whenHas(
                'created_at',
                fn () => $this->created_at?->toIso8601String()
            ),
            'updated_at' => $this->whenHas(
                'updated_at',
                fn () => $this->updated_at?->toIso8601String()
            ),
        ];
    }
}
