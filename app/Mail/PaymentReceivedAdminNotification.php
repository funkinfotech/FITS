<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceivedAdminNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Payment $payment)
    {
    }

    public function envelope(): Envelope
    {
        $invoice = $this->payment->invoice;

        return new Envelope(
            subject: "Payment received — {$invoice->bill_to_name} — {$invoice->invoice_number} — \${$this->payment->amount}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-received-admin-notification',
            text: 'emails.payment-received-admin-notification-text',
            with: [
                'payment' => $this->payment,
                'invoice' => $this->payment->invoice,
                'paymentUrl' => route('filament.admin.resources.payments.view', $this->payment),
            ],
        );
    }

    public function attachments(): array
    {
        if (! $this->payment->pdf_path) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('local', $this->payment->pdf_path)
                ->as("{$this->payment->receipt_number}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
