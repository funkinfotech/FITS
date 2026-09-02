<?php

namespace Tests\Feature;

use App\Models\InvoiceCounter;
use App\Support\InvoiceNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InvoiceNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_sequential_calls_produce_gapless_numbers_for_the_current_year(): void
    {
        Carbon::setTestNow('2026-07-29');

        $numbers = [];

        for ($i = 0; $i < 25; $i++) {
            $numbers[] = InvoiceNumberGenerator::next()['number'];
        }

        $expected = array_map(fn ($i) => sprintf('INV-2026-%04d', $i), range(1, 25));

        $this->assertSame($expected, $numbers);
        $this->assertSame(25, InvoiceCounter::where('year', 2026)->value('last_sequence'));

        Carbon::setTestNow();
    }

    public function test_sequence_resets_for_a_new_year_without_touching_the_prior_year(): void
    {
        Carbon::setTestNow('2026-12-31');
        InvoiceNumberGenerator::next();
        InvoiceNumberGenerator::next();

        Carbon::setTestNow('2027-01-01');
        $next = InvoiceNumberGenerator::next();

        $this->assertSame('INV-2027-0001', $next['number']);
        $this->assertSame(2, InvoiceCounter::where('year', 2026)->value('last_sequence'));
        $this->assertSame(1, InvoiceCounter::where('year', 2027)->value('last_sequence'));

        Carbon::setTestNow();
    }
}
