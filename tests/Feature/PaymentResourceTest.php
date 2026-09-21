<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function makePayment(string $companyName, string $receiptNumber, int $sequence, PaymentMethod $method): Payment
    {
        $company = Company::create(['name' => $companyName]);
        $invoice = Invoice::create(['company_id' => $company->id]);

        return Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => $receiptNumber,
            'year' => 2026,
            'sequence' => $sequence,
            'amount' => '150.00',
            'paid_date' => '2026-09-20',
            'method' => $method->value,
        ]);
    }

    public function test_list_page_shows_payments(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $payment = $this->makePayment('Acme Corp', 'RCPT-2026-0001', 1, PaymentMethod::Check);

        Livewire::test(ListPayments::class)
            ->assertCanSeeTableRecords([$payment]);
    }

    public function test_method_filter_narrows_results(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $check = $this->makePayment('Acme Corp', 'RCPT-2026-0001', 1, PaymentMethod::Check);
        $cash = $this->makePayment('Beta LLC', 'RCPT-2026-0002', 2, PaymentMethod::Cash);

        Livewire::test(ListPayments::class)
            ->filterTable('method', PaymentMethod::Cash->value)
            ->assertCanSeeTableRecords([$cash])
            ->assertCanNotSeeTableRecords([$check]);
    }

    public function test_voided_payments_are_hidden_by_default_but_visible_when_the_toggle_is_off(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $active = $this->makePayment('Acme Corp', 'RCPT-2026-0001', 1, PaymentMethod::Check);
        $voided = $this->makePayment('Beta LLC', 'RCPT-2026-0002', 2, PaymentMethod::Cash);
        $voided->forceFill(['voided_at' => now()])->saveQuietly();

        Livewire::test(ListPayments::class)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$voided])
            ->filterTable('hide_voided', false)
            ->assertCanSeeTableRecords([$active, $voided]);
    }
}
