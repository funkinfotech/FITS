<?php

namespace Tests\Feature;

use App\Models\PaymentCounter;
use App\Support\PaymentNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PaymentNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_sequential_calls_produce_gapless_numbers_for_the_current_year(): void
    {
        Carbon::setTestNow('2026-09-20');

        $numbers = [];

        for ($i = 0; $i < 10; $i++) {
            $numbers[] = PaymentNumberGenerator::next()['number'];
        }

        $expected = array_map(fn ($i) => sprintf('RCPT-2026-%04d', $i), range(1, 10));

        $this->assertSame($expected, $numbers);
        $this->assertSame(10, PaymentCounter::where('year', 2026)->value('last_sequence'));

        Carbon::setTestNow();
    }

    public function test_sequence_resets_for_a_new_year_without_touching_the_prior_year(): void
    {
        Carbon::setTestNow('2026-12-31');
        PaymentNumberGenerator::next();
        PaymentNumberGenerator::next();

        Carbon::setTestNow('2027-01-01');
        $next = PaymentNumberGenerator::next();

        $this->assertSame('RCPT-2027-0001', $next['number']);
        $this->assertSame(2, PaymentCounter::where('year', 2026)->value('last_sequence'));
        $this->assertSame(1, PaymentCounter::where('year', 2027)->value('last_sequence'));

        Carbon::setTestNow();
    }
}
