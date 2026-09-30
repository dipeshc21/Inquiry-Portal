<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Http\Requests\StoreNoteRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\NoteResource;
use App\Models\Inquiry;
use App\Models\Note;
use App\Services\InquiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
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

        $notes = $inquiry->notes()
            ->with('user:id,name')
            ->paginate($data['per_page'] ?? 15);

        return ApiResponse::success(
            NoteResource::collection($notes->getCollection()),
            'Notes retrieved successfully.',
            [
                'current_page' => $notes->currentPage(),
                'last_page' => $notes->lastPage(),
                'per_page' => $notes->perPage(),
                'total' => $notes->total(),
                'from' => $notes->firstItem(),
                'to' => $notes->lastItem(),
            ]
        );
    }

    public function store(
        StoreNoteRequest $request,
        Inquiry $inquiry
    ): JsonResponse {
        $note = $this->inquiries->addNote(
            $inquiry,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new NoteResource($note),
            'Internal note added successfully.'
        )->response()->setStatusCode(201);
    }

    public function update(
        StoreNoteRequest $request,
        Note $note
    ): ApiResponse {
        return ApiResponse::success(
            new NoteResource(
                $this->inquiries->updateNote(
                    $note,
                    $request->validated(),
                    $request->user()
                )
            ),
            'Internal note updated successfully.'
        );
    }

    public function destroy(Request $request, Note $note): ApiResponse
    {
        $this->inquiries->deleteNote($note, $request->user());

        return ApiResponse::success(null, 'Internal note deleted successfully.');
    }
}
