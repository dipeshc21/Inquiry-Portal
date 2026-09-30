<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use OverflowException;

class ReferenceNumberService
{
    public function next(?CarbonInterface $date = null): string
    {
        $date ??= now();
        $sequenceDate = $date->format('Y-m-d');
        $referenceDate = $date->format('Ymd');

        return DB::transaction(function () use (
            $sequenceDate,
            $referenceDate
        ): string {
            DB::table('reference_sequences')->insertOrIgnore([
                'date' => $sequenceDate,
                'last_number' => 0,
            ]);

            $sequence = DB::table('reference_sequences')
                ->where('date', $sequenceDate)
                ->lockForUpdate()
                ->first();

            $number = (int) $sequence->last_number + 1;

            if ($number > 9999) {
                throw new OverflowException(
                    'The daily inquiry reference capacity has been reached.'
                );
            }

            DB::table('reference_sequences')
                ->where('date', $sequenceDate)
                ->update(['last_number' => $number]);

            return sprintf('INQ-%s-%04d', $referenceDate, $number);
        });
    }
}
