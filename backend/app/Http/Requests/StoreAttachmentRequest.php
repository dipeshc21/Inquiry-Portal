<?php

namespace App\Http\Requests;

class StoreAttachmentRequest extends ApiRequest
{
    public function rules(): array
    {
        $extensions = implode(
            ',',
            config('inquiry.uploads.extensions')
        );

        return [
            'files' => [
                'required',
                'array',
                'min:1',
                'max:'.config('inquiry.uploads.max_files', 3),
            ],
            'files.*' => [
                'required',
                'file',
                'mimes:'.$extensions,
                'extensions:'.$extensions,
                'max:'.config('inquiry.uploads.max_size_kb', 5120),
            ],
            'uploaded_by' => ['prohibited'],
            'stored_path' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'files.required' => 'Select at least one attachment.',
            'files.max' => 'Upload at most 3 attachments per request.',
        ];
    }
}
