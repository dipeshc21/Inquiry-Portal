<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicInquiryRequest;
use App\Http\Resources\ApiResponse;
use App\Services\InquiryService;
use Illuminate\Http\JsonResponse;

class PublicInquiryController extends Controller
{
    public function __construct(
        private readonly InquiryService $inquiries
    ) {
    }

    public function store(PublicInquiryRequest $request): JsonResponse
    {
        $inquiry = $this->inquiries->createPublic(
            $request->validated(),
            $request->file('files', []),
            $request->ip()
        );

        return ApiResponse::success([
            'reference_no' => $inquiry->reference_no,
        ], 'Your inquiry has been received. We will contact you shortly.')
            ->response()
            ->setStatusCode(201);
    }
}
