<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Resources\ApiResponse;
use App\Services\SettingsService;

class SettingController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings
    ) {
    }

    public function show(): ApiResponse
    {
        $this->authorize('manage-settings');

        return ApiResponse::success(
            $this->settings->assignment(),
            'Assignment settings retrieved successfully.'
        );
    }

    public function update(UpdateSettingsRequest $request): ApiResponse
    {
        return ApiResponse::success(
            $this->settings->updateAssignment(
                $request->validated(),
                $request->user()
            ),
            'Assignment settings updated successfully.'
        );
    }
}
