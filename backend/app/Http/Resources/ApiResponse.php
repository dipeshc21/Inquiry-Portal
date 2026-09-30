<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use stdClass;

class ApiResponse extends JsonResource
{
    public static $wrap = null;

    public function __construct(
        mixed $resource = null,
        private readonly string $message = 'Request completed successfully.',
        private readonly bool $successful = true,
        private readonly array $metadata = [],
        private readonly array $validationErrors = [],
    ) {
        parent::__construct($resource);
    }

    public static function success(
        mixed $data = null,
        string $message = 'Request completed successfully.',
        array $meta = [],
    ): self {
        return new self(
            resource: $data,
            message: $message,
            metadata: $meta,
        );
    }

    public static function failure(
        string $message,
        array $errors = [],
    ): self {
        return new self(
            message: $message,
            successful: false,
            validationErrors: $errors,
        );
    }

    public function toArray(Request $request): array
    {
        if (! $this->successful) {
            return [
                'success' => false,
                'message' => $this->message,
                'errors' => $this->validationErrors ?: new stdClass(),
            ];
        }

        return [
            'success' => true,
            'message' => $this->message,
            'data' => $this->normalize($this->resource, $request),
            'meta' => $this->metadata ?: new stdClass(),
        ];
    }

    private function normalize(mixed $value, Request $request): mixed
    {
        if ($value instanceof JsonResource) {
            return $value->resolve($request);
        }

        if ($value instanceof Arrayable) {
            return $this->normalize($value->toArray(), $request);
        }

        if ($value instanceof JsonSerializable) {
            return $this->normalize($value->jsonSerialize(), $request);
        }

        if (is_array($value)) {
            $result = [];

            foreach ($value as $key => $item) {
                $result[$key] = $this->normalize($item, $request);
            }

            return $result;
        }

        return $value;
    }
}
