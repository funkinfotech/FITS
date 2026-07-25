<?php

namespace App\Mail;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class TicketReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Comment $comment)
    {
    }

    public function envelope(): Envelope
    {
        $ticket = $this->comment->ticket;

        return new Envelope(
            subject: "Re: [Ticket #{$ticket->ticket_number}] {$ticket->subject}",
            replyTo: ["helpdesk+{$ticket->ticket_number}@support.funkinfotech.com"],
        );
    }

    public function content(): Content
    {
        $ticket = $this->comment->ticket;

        return new Content(
            view: 'emails.ticket-reply',
            text: 'emails.ticket-reply-text',
            with: [
                'ticket' => $ticket,
                'comment' => $this->comment,
                'ticketUrl' => URL::signedRoute('tickets.guest-view', ['ticket' => $ticket->id]),
            ],
        );
    }
}
