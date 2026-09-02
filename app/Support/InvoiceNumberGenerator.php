<?php

namespace App\Support;

use App\Models\InvoiceCounter;
use Illuminate\Support\Facades\DB;

class InvoiceNumberGenerator
{
    public static function next(?int $year = null): array
    {
        $year ??= (int) now()->format('Y');

        return DB::transaction(function () use ($year) {
            $counter = InvoiceCounter::where('year', $year)->lockForUpdate()->first()
                ?? InvoiceCounter::create(['year' => $year, 'last_sequence' => 0]);

            $counter = InvoiceCounter::where('year', $year)->lockForUpdate()->first();
            $counter->increment('last_sequence');

            return [
                'year' => $year,
                'sequence' => $counter->last_sequence,
                'number' => sprintf('INV-%d-%04d', $year, $counter->last_sequence),
            ];
        });
    }
}
