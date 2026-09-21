<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentDownloadControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function makePayment(): Payment
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $invoice = Invoice::create(['company_id' => $company->id]);

        return Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCPT-2026-0001',
            'year' => 2026,
            'sequence' => 1,
            'amount' => '100.00',
            'paid_date' => '2026-09-20',
            'method' => PaymentMethod::Cash->value,
        ]);
    }

    public function test_admin_can_download_a_generated_receipt(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['is_admin' => true]);
        $payment = $this->makePayment();
        $payment->update(['pdf_path' => 'payments/2026/RCPT-2026-0001.pdf']);
        Storage::disk('local')->put($payment->pdf_path, 'fake-pdf-contents');

        $this->actingAs($admin)
            ->get(route('payments.download', $payment))
            ->assertOk();
    }

    public function test_non_admin_is_redirected_to_access_denied(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $payment = $this->makePayment();

        $this->actingAs($user)
            ->get(route('payments.download', $payment))
            ->assertRedirect(route('access-denied'));
    }
}
