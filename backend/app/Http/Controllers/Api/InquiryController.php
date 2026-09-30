<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignInquiryRequest;
use App\Http\Requests\InquiryIndexRequest;
use App\Http\Requests\UpdateInquiryRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\InquiryCollection;
use App\Http\Resources\InquiryResource;
use App\Models\Inquiry;
use App\Services\ExportService;
use App\Services\InquiryService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InquiryController extends Controller
{
    public function __construct(
        private readonly InquiryService $inquiries,
        private readonly ExportService $exports
    ) {
    }

    public function index(InquiryIndexRequest $request): InquiryCollection
    {
        return new InquiryCollection(
            $this->inquiries->paginate(
                $request->validated(),
                $request->user()
            )
        );
    }

    public function show(Request $request, Inquiry $inquiry): ApiResponse
    {
        return ApiResponse::success(
            new InquiryResource(
                $this->inquiries->detail($inquiry, $request->user())
            ),
            'Inquiry retrieved successfully.'
        );
    }

    public function update(
        UpdateInquiryRequest $request,
        Inquiry $inquiry
    ): ApiResponse {
        return ApiResponse::success(
            new InquiryResource(
                $this->inquiries->update(
                    $inquiry,
                    $request->validated(),
                    $request->user()
                )
            ),
            'Inquiry updated successfully.'
        );
    }

    public function status(
        UpdateStatusRequest $request,
        Inquiry $inquiry
    ): ApiResponse {
        $data = $request->validated();

        return ApiResponse::success(
            new InquiryResource(
                $this->inquiries->changeStatus(
                    $inquiry,
                    $data['status'],
                    $data['note'] ?? null,
                    $request->user()
                )
            ),
            'Inquiry status updated successfully.'
        );
    }

    public function assign(
        AssignInquiryRequest $request,
        Inquiry $inquiry
    ): ApiResponse {
        $data = $request->validated();

        return ApiResponse::success(
            new InquiryResource(
                $this->inquiries->assign(
                    $inquiry,
                    $data['assigned_to'] === null
                        ? null
                        : (int) $data['assigned_to'],
                    $request->user()
                )
            ),
            'Inquiry assignment updated successfully.'
        );
    }

    public function destroy(Request $request, Inquiry $inquiry): ApiResponse
    {
        $this->inquiries->delete($inquiry, $request->user());

        return ApiResponse::success(null, 'Inquiry deleted successfully.');
    }

    public function export(InquiryIndexRequest $request): StreamedResponse
    {
        return $this->exports->inquiries(
            $request->validated(),
            $request->user()
        );
    }
}
