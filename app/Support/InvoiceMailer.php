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
        if (! $invoice->pdf_path) {
            InvoicePdfGenerator::generate($invoice);
        }

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
        if (! $invoice->pdf_path) {
            InvoicePdfGenerator::generate($invoice);
        }

        foreach ($contacts as $contact) {
            if (! $contact->email) {
                continue;
            }

            Mail::to($contact->email)->queue(new InvoiceOverdueReminderMail($invoice));
        }

        $invoice->forceFill(['overdue_reminder_sent_at' => now()])->saveQuietly();
    }
}
