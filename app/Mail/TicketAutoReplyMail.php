<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class TicketAutoReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Ticket $ticket)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Re: [Ticket #{$this->ticket->ticket_number}] {$this->ticket->subject}",
            replyTo: ["helpdesk+{$this->ticket->ticket_number}@support.funkinfotech.com"],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-auto-reply',
            text: 'emails.ticket-auto-reply-text',
            with: [
                'ticket' => $this->ticket,
                'firstName' => $this->ticket->contact?->first_name,
                'ticketUrl' => URL::signedRoute('tickets.guest-view', ['ticket' => $this->ticket->id]),
            ],
        );
    }
}
