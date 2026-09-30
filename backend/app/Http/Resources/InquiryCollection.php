<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class InquiryCollection extends ResourceCollection
{
    public $collects = InquiryResource::class;

    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Inquiries retrieved successfully.',
            'data' => $this->collection,
        ];
    }

    public function paginationInformation(
        Request $request,
        array $paginated,
        array $default
    ): array {
        return [
            'meta' => [
                'current_page' => $paginated['current_page'],
                'last_page' => $paginated['last_page'],
                'per_page' => $paginated['per_page'],
                'total' => $paginated['total'],
                'from' => $paginated['from'],
                'to' => $paginated['to'],
            ],
        ];
    }
}
