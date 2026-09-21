<?php

namespace App\Support;

use App\Models\PaymentCounter;
use Illuminate\Support\Facades\DB;

class PaymentNumberGenerator
{
    public static function next(?int $year = null): array
    {
        $year ??= (int) now()->format('Y');

        return DB::transaction(function () use ($year) {
            $counter = PaymentCounter::where('year', $year)->lockForUpdate()->first()
                ?? PaymentCounter::create(['year' => $year, 'last_sequence' => 0]);

            $counter = PaymentCounter::where('year', $year)->lockForUpdate()->first();
            $counter->increment('last_sequence');

            return [
                'year' => $year,
                'sequence' => $counter->last_sequence,
                'number' => sprintf('RCPT-%d-%04d', $year, $counter->last_sequence),
            ];
        });
    }
}
