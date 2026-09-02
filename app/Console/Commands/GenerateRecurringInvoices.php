<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\RecurringCharge;
use App\Support\InvoiceMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateRecurringInvoices extends Command
{
    protected $signature = 'invoices:generate-recurring';

    protected $description = 'Generate this cycle\'s invoice for each active recurring charge, rolling forward any unpaid prior balance';

    public function handle(): int
    {
        $today = today();
        $generated = 0;

        RecurringCharge::query()
            ->where('is_active', true)
            ->with('company')
            ->chunkById(50, function ($charges) use ($today, &$generated) {
                foreach ($charges as $charge) {
                    if ($charge->isDue($today)) {
                        $this->generateFor($charge, $today);
                        $generated++;
                    }
                }
            });

        $this->info("Generated {$generated} recurring invoice(s).");

        return self::SUCCESS;
    }

    protected function generateFor(RecurringCharge $charge, \Illuminate\Support\Carbon $today): void
    {
        DB::transaction(function () use ($charge, $today) {
            $unpaidPrior = Invoice::query()
                ->where('recurring_charge_id', $charge->id)
                ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
                ->get();

            $invoice = Invoice::create([
                'company_id' => $charge->company_id,
                'recurring_charge_id' => $charge->id,
            ]);

            $invoice->lineItems()->create([
                'description' => $charge->description,
                'quantity' => 1,
                'unit_price' => $charge->amount,
                'sort' => 0,
            ]);

            foreach ($unpaidPrior as $index => $priorInvoice) {
                $invoice->lineItems()->create([
                    'description' => "Previous balance due (Invoice {$priorInvoice->invoice_number})",
                    'quantity' => 1,
                    'unit_price' => $priorInvoice->total,
                    'sort' => $index + 1,
                ]);

                $priorInvoice->forceFill([
                    'status' => InvoiceStatus::RolledOver,
                    'rolled_into_invoice_id' => $invoice->id,
                ])->saveQuietly();
            }

            $invoice->recalculateTotal()->save();

            $charge->forceFill(['last_invoiced_on' => $charge->nextDueDate()])->save();

            $contacts = $charge->company->contacts;

            if ($contacts->isNotEmpty()) {
                InvoiceMailer::send($invoice, $contacts);
            }
        });
    }
}
