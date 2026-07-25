<?php

namespace App\Support;

use App\Mail\TicketReplyMail;
use App\Models\Comment;
use Illuminate\Support\Facades\Mail;

class TicketMailer
{
    public static function sendReply(Comment $comment): void
    {
        foreach ($comment->recipients as $contact) {
            Mail::to($contact->email)->queue(new TicketReplyMail($comment));
        }
    }
}
