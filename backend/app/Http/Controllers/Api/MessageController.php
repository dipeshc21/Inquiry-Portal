<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\MessageResource;
use App\Models\Inquiry;
use App\Services\InquiryService;
use Illuminate\Http\JsonResponse;

class MessageController extends Controller
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

        $messages = $inquiry->messages()
            ->with('user:id,name')
            ->paginate($data['per_page'] ?? 30);

        return ApiResponse::success(
            MessageResource::collection($messages->getCollection()),
            'Messages retrieved successfully.',
            [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
                'from' => $messages->firstItem(),
                'to' => $messages->lastItem(),
            ]
        );
    }

    public function store(
        StoreMessageRequest $request,
        Inquiry $inquiry
    ): JsonResponse {
        $message = $this->inquiries->addMessage(
            $inquiry,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new MessageResource($message),
            'Reply saved and queued for email delivery.'
        )->response()->setStatusCode(201);
    }
}
