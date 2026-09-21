<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Payment $payment)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Payment Receipt — Invoice {$this->payment->invoice->invoice_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-receipt',
            text: 'emails.payment-receipt-text',
            with: [
                'payment' => $this->payment,
                'invoice' => $this->payment->invoice,
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
