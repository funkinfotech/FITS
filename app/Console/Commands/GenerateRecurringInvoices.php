<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\RecurringCharge;
use App\Support\InvoiceMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateRecurringInvoices extends Command
{
    protected $signature = 'invoices:generate-recurring';

    protected $description = 'Generate this cycle\'s invoice for each active recurring charge';

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
                        $this->generateFor($charge);
                        $generated++;
                    }
                }
            });

        $this->info("Generated {$generated} recurring invoice(s).");

        return self::SUCCESS;
    }

    /**
     * Each cycle's invoice stands on its own: it only ever bills for that
     * cycle's charge. Any older unpaid invoices for the company are left
     * completely untouched — they keep their own due date and status, and
     * are surfaced to the customer as a "previous balance" line on this
     * invoice's PDF/email (see Invoice::getPreviousBalanceAttribute()) and
     * to the admin via the overdue-notification/reminder flow, not by
     * folding their dollar amount into this invoice.
     */
    protected function generateFor(RecurringCharge $charge): void
    {
        DB::transaction(function () use ($charge) {
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

            $invoice->recalculateTotal()->save();

            $charge->forceFill(['last_invoiced_on' => $charge->nextDueDate()])->save();

            $contacts = $charge->company->contacts;

            if ($contacts->isNotEmpty()) {
                InvoiceMailer::send($invoice, $contacts);
            }
        });
    }
}
