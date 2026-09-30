<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    public function inquiries(
        array $filters,
        User $actor
    ): StreamedResponse {
        Gate::forUser($actor)->authorize('export', Inquiry::class);

        $filename = 'inquiries-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use (
            $filters,
            $actor
        ): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                throw new RuntimeException('Unable to open the export stream.');
            }

            try {
                // UTF-8 BOM supports common spreadsheet applications.
                fwrite($output, "\xEF\xBB\xBF");

                fputcsv($output, [
                    'Reference',
                    'Name',
                    'Email',
                    'Phone',
                    'Company',
                    'Subject',
                    'Source',
                    'Status',
                    'Priority',
                    'Assignee',
                    'Created At',
                ], ',', '"', '');

                $query = Inquiry::query()
                    ->select(Inquiry::LIST_COLUMNS)
                    ->visibleTo($actor)
                    ->filter($filters)
                    ->sorted($filters)
                    ->with('assignee:id,name');

                // Chunked eager loading avoids loading the full export or
                // issuing an assignee query for each individual inquiry.
                foreach ($query->lazy(500) as $inquiry) {
                    $row = [
                        $inquiry->reference_no,
                        $inquiry->name,
                        $inquiry->email,
                        $inquiry->phone,
                        $inquiry->company,
                        $inquiry->subject,
                        $inquiry->source,
                        $inquiry->status,
                        $inquiry->priority,
                        $inquiry->assignee?->name,
                        $inquiry->created_at?->toIso8601String(),
                    ];

                    fputcsv(
                        $output,
                        array_map($this->safeCell(...), $row),
                        ',',
                        '"',
                        ''
                    );
                }
            } finally {
                fclose($output);
            }
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function safeCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        // Spreadsheet programs can interpret customer-controlled text as a
        // formula, including formulas preceded by whitespace.
        if (preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $value) === 1) {
            return "'".$value;
        }

        if (preg_match('/^[\t\r\n]/', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
