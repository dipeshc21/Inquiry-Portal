<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AttachmentService
{
    public function __construct(
        private readonly ActivityLogger $activityLogger
    ) {
    }

    /**
     * @param array<int, UploadedFile> $files
     * @return Collection<int, Attachment>
     */
    public function store(
        Inquiry $inquiry,
        array $files,
        ?User $actor = null
    ): Collection {
        if ($actor !== null) {
            Gate::forUser($actor)->authorize('update', $inquiry);
        }

        $storedPaths = [];

        try {
            return DB::transaction(function () use (
                $inquiry,
                $files,
                $actor,
                &$storedPaths
            ): Collection {
                $attachments = collect();

                foreach ($files as $file) {
                    $path = $file->store(
                        config('inquiry.uploads.directory', 'attachments')
                            .'/'.$inquiry->id,
                        $this->disk()
                    );

                    if (! is_string($path) || $path === '') {
                        throw new RuntimeException(
                            'The attachment could not be stored.'
                        );
                    }

                    $storedPaths[] = $path;

                    $attachment = $inquiry->attachments()->create([
                        'uploaded_by' => $actor?->id,
                        'original_name' => $this->safeName(
                            $file->getClientOriginalName()
                        ),
                        'stored_path' => $path,
                        'mime_type' => $file->getMimeType()
                            ?: 'application/octet-stream',
                        'size' => $file->getSize(),
                    ]);

                    $this->activityLogger->log(
                        action: 'attachment_uploaded',
                        description: 'Attachment uploaded: '
                            .$attachment->original_name.'.',
                        inquiry: $inquiry,
                        actor: $actor,
                        newValues: [
                            'attachment_id' => $attachment->id,
                            'original_name' => $attachment->original_name,
                            'mime_type' => $attachment->mime_type,
                            'size' => $attachment->size,
                        ]
                    );

                    $attachments->push($attachment);
                }

                return $attachments;
            });
        } catch (Throwable $exception) {
            $this->cleanup($storedPaths);

            throw $exception;
        }
    }

    public function download(
        Attachment $attachment,
        User $actor
    ): StreamedResponse {
        $attachment->loadMissing('inquiry');
        $inquiry = $attachment->inquiry;

        abort_if($inquiry === null, 404);
        Gate::forUser($actor)->authorize('view', $inquiry);

        $disk = Storage::disk($this->disk());

        abort_unless($disk->exists($attachment->stored_path), 404);

        return $disk->download(
            $attachment->stored_path,
            $this->safeName($attachment->original_name),
            [
                'Content-Type' => $attachment->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }

    public function delete(Attachment $attachment, User $actor): void
    {
        $attachment->loadMissing('inquiry');
        $inquiry = $attachment->inquiry;

        abort_if($inquiry === null, 404);
        Gate::forUser($actor)->authorize('update', $inquiry);

        DB::transaction(function () use (
            $attachment,
            $inquiry,
            $actor
        ): void {
            $path = $attachment->stored_path;

            $this->activityLogger->log(
                action: 'attachment_deleted',
                description: 'Attachment deleted: '
                    .$attachment->original_name.'.',
                inquiry: $inquiry,
                actor: $actor,
                oldValues: [
                    'attachment_id' => $attachment->id,
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                ]
            );

            $attachment->delete();

            // Do not remove the file before the database transaction commits.
            DB::afterCommit(fn () => $this->cleanup([$path]));
        });
    }

    /**
     * Used when an outer inquiry transaction rolls back after files were saved.
     *
     * @param array<int, string> $paths
     */
    public function cleanup(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                $disk = Storage::disk($this->disk());

                if ($disk->exists($path) && ! $disk->delete($path)) {
                    Log::warning('Attachment cleanup returned false.', [
                        'path' => $path,
                    ]);
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    private function disk(): string
    {
        return (string) config('inquiry.uploads.disk', 'public');
    }

    private function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';

        return mb_substr($name !== '' ? $name : 'attachment', 0, 200);
    }
}
