<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Http\Requests\ReminderIndexRequest;
use App\Http\Requests\StoreReminderRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\ReminderResource;
use App\Models\Inquiry;
use App\Models\Reminder;
use App\Services\InquiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function __construct(
        private readonly InquiryService $inquiries
    ) {
    }

    public function index(
        PaginationRequest $request,
        Inquiry $inquiry
    ): ApiResponse {
        $this->authorize('view', $inquiry);

        $data = $request->validated();

        $reminders = $inquiry->reminders()
            ->with('user:id,name')
            ->paginate($data['per_page'] ?? 15);

        return ApiResponse::success(
            ReminderResource::collection($reminders->getCollection()),
            'Reminders retrieved successfully.',
            [
                'current_page' => $reminders->currentPage(),
                'last_page' => $reminders->lastPage(),
                'per_page' => $reminders->perPage(),
                'total' => $reminders->total(),
                'from' => $reminders->firstItem(),
                'to' => $reminders->lastItem(),
            ]
        );
    }

    public function upcoming(ReminderIndexRequest $request): ApiResponse
    {
        $data = $request->validated();

        $query = Reminder::query()
            ->forUser($request->user())
            ->accessibleTo($request->user())
            ->with([
                'user:id,name',
                'inquiry:id,reference_no,subject,assigned_to',
            ]);

        // The default includes both overdue and future incomplete reminders.
        // Explicit tabs can request upcoming, overdue, or completed.
        $status = $data['status'] ?? 'all';

        if ($status === 'upcoming') {
            $query->upcoming();
        } elseif ($status === 'overdue') {
            $query->overdue();
        } elseif ($status === 'completed') {
            $query->where('is_completed', true);
        } else {
            $query->incomplete();
        }

        if (! empty($data['q'])) {
            $search = str_replace(
                ['!', '%', '_'],
                ['!!', '!%', '!_'],
                $data['q']
            );

            $query->whereRaw(
                "title LIKE ? ESCAPE '!'",
                ['%'.$search.'%']
            );
        }

        $reminders = $query
            ->orderBy('remind_at')
            ->orderBy('id')
            ->paginate($data['per_page'] ?? 15);

        return ApiResponse::success(
            ReminderResource::collection($reminders->getCollection()),
            'Your reminders retrieved successfully.',
            [
                'current_page' => $reminders->currentPage(),
                'last_page' => $reminders->lastPage(),
                'per_page' => $reminders->perPage(),
                'total' => $reminders->total(),
                'from' => $reminders->firstItem(),
                'to' => $reminders->lastItem(),
            ]
        );
    }

    public function store(
        StoreReminderRequest $request,
        Inquiry $inquiry
    ): JsonResponse {
        $reminder = $this->inquiries->addReminder(
            $inquiry,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new ReminderResource($reminder),
            'Reminder created successfully.'
        )->response()->setStatusCode(201);
    }

    public function complete(
        Request $request,
        Reminder $reminder
    ): ApiResponse {
        return ApiResponse::success(
            new ReminderResource(
                $this->inquiries->completeReminder(
                    $reminder,
                    $request->user()
                )
            ),
            'Reminder marked complete.'
        );
    }

    public function destroy(
        Request $request,
        Reminder $reminder
    ): ApiResponse {
        $this->inquiries->deleteReminder($reminder, $request->user());

        return ApiResponse::success(null, 'Reminder deleted successfully.');
    }
}
