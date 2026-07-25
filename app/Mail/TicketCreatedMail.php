<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class TicketCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Ticket $ticket)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Ticket #{$this->ticket->ticket_number}] {$this->ticket->subject}",
            replyTo: ["helpdesk+{$this->ticket->ticket_number}@support.funkinfotech.com"],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-created',
            text: 'emails.ticket-created-text',
            with: [
                'ticket' => $this->ticket,
                'ticketUrl' => URL::signedRoute('tickets.guest-view', ['ticket' => $this->ticket->id]),
            ],
        );
    }
}
