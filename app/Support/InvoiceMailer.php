<?php

namespace App\Support;

use App\Enums\InvoiceStatus;
use App\Mail\InvoiceMail;
use App\Mail\InvoiceOverdueReminderMail;
use App\Models\Invoice;
use Illuminate\Support\Facades\Mail;

class InvoiceMailer
{
    public static function send(Invoice $invoice, iterable $contacts, ?string $customMessage = null): void
    {
        // Always regenerate: the PDF includes a live "previous balance" summary
        // of the company's other open invoices, which can change between sends
        // (e.g. an older invoice becomes overdue, or a resend happens after a
        // new one was created) even though this invoice's own line items didn't.
        InvoicePdfGenerator::generate($invoice);

        foreach ($contacts as $contact) {
            if (! $contact->email) {
                continue;
            }

            Mail::to($contact->email)->queue(new InvoiceMail($invoice, $customMessage));
        }

        $invoice->forceFill([
            'sent_at' => now(),
            'status' => $invoice->status === InvoiceStatus::Draft ? InvoiceStatus::Sent : $invoice->status,
        ])->saveQuietly();
    }

    public static function sendOverdueReminder(Invoice $invoice, iterable $contacts): void
    {
        InvoicePdfGenerator::generate($invoice);

        foreach ($contacts as $contact) {
            if (! $contact->email) {
                continue;
            }

            Mail::to($contact->email)->queue(new InvoiceOverdueReminderMail($invoice));
        }

        $invoice->forceFill(['overdue_reminder_sent_at' => now()])->saveQuietly();
    }
}
