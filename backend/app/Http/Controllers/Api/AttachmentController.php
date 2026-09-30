<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttachmentRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Inquiry;
use App\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function __construct(
        private readonly AttachmentService $attachments
    ) {
    }

    public function store(
        StoreAttachmentRequest $request,
        Inquiry $inquiry
    ): JsonResponse {
        $this->authorize('update', $inquiry);

        $attachments = $this->attachments->store(
            $inquiry,
            $request->file('files', []),
            $request->user()
        );

        // Eager load all uploader relationships in one query.
        $attachments = (new \Illuminate\Database\Eloquent\Collection(
            $attachments->all()
        ))->load('uploader:id,name');

        return ApiResponse::success(
            AttachmentResource::collection($attachments),
            'Attachments uploaded successfully.'
        )->response()->setStatusCode(201);
    }

    public function download(
        Request $request,
        Attachment $attachment
    ): StreamedResponse {
        return $this->attachments->download(
            $attachment,
            $request->user()
        );
    }

    public function destroy(
        Request $request,
        Attachment $attachment
    ): ApiResponse {
        $this->attachments->delete($attachment, $request->user());

        return ApiResponse::success(null, 'Attachment deleted successfully.');
    }
}
