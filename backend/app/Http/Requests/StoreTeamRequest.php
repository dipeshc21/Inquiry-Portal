<?php

namespace App\Http\Requests;

use App\Models\Team;
use Illuminate\Validation\Rule;

class StoreTeamRequest extends ApiRequest
{
    public function rules(): array
    {
        $target = $this->route('team');
        $targetId = $target instanceof Team ? $target->id : $target;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('teams', 'name')->ignore($targetId),
            ],
        ];
    }
}
