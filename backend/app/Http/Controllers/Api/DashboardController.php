<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponse;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard
    ) {
    }

    public function stats(Request $request): ApiResponse
    {
        $this->authorize('view-dashboard');

        return ApiResponse::success(
            $this->dashboard->stats($request->user()),
            'Dashboard statistics retrieved successfully.'
        );
    }
}
