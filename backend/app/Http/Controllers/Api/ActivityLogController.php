<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivityLogIndexRequest;
use App\Http\Requests\PaginationRequest;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\ApiResponse;
use App\Models\ActivityLog;
use App\Models\Inquiry;
use Carbon\CarbonImmutable;

class ActivityLogController extends Controller
{
    public function index(ActivityLogIndexRequest $request): ApiResponse
    {
        $this->authorize('view-activity-logs');

        $data = $request->validated();

        $query = ActivityLog::query()->with([
            'user:id,name',
            'inquiry:id,reference_no,subject,deleted_at',
        ]);

        foreach (['inquiry_id', 'user_id', 'action'] as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $query->where($field, $data[$field]);
            }
        }

        if (! empty($data['date_from'])) {
            $query->where(
                'created_at',
                '>=',
                CarbonImmutable::parse($data['date_from'])->startOfDay()
            );
        }

        if (! empty($data['date_to'])) {
            $query->where(
                'created_at',
                '<',
                CarbonImmutable::parse($data['date_to'])
                    ->startOfDay()
                    ->addDay()
            );
        }

        if (! empty($data['q'])) {
            $search = str_replace(
                ['!', '%', '_'],
                ['!!', '!%', '!_'],
                $data['q']
            );

            $query->whereRaw(
                "description LIKE ? ESCAPE '!'",
                ['%'.$search.'%']
            );
        }

        $logs = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 15);

        return ApiResponse::success(
            ActivityLogResource::collection($logs->getCollection()),
            'Activity log retrieved successfully.',
            [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
            ]
        );
    }

    public function inquiry(
        PaginationRequest $request,
        Inquiry $inquiry
    ): ApiResponse {
        $this->authorize('view', $inquiry);

        $data = $request->validated();

        $logs = $inquiry->activityLogs()
            ->with('user:id,name')
            ->paginate($data['per_page'] ?? 30);

        return ApiResponse::success(
            ActivityLogResource::collection($logs->getCollection()),
            'Inquiry activity retrieved successfully.',
            [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
            ]
        );
    }
}
