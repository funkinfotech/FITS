<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceOverdueAdminNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invoice {$this->invoice->invoice_number} is overdue",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice-overdue-admin-notification',
            text: 'emails.invoice-overdue-admin-notification-text',
            with: [
                'invoice' => $this->invoice,
                'invoiceUrl' => route('filament.admin.resources.invoices.edit', $this->invoice),
            ],
        );
    }
}
