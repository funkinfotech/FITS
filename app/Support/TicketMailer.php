<?php

namespace App\Support;

use App\Mail\TicketCreatedMail;
use App\Mail\TicketReplyMail;
use App\Models\Comment;
use App\Models\Ticket;
use Illuminate\Support\Facades\Mail;

class TicketMailer
{
    public static function sendReply(Comment $comment): void
    {
        foreach ($comment->recipients as $contact) {
            Mail::to($contact->email)->queue(new TicketReplyMail($comment));
        }
    }

    public static function sendTicketCreated(Ticket $ticket): void
    {
        if (! $ticket->contact) {
            return;
        }

        Mail::to($ticket->contact->email)->queue(new TicketCreatedMail($ticket));
    }
}
