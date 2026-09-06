<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Support\StoresAttachments;

class CommentController extends Controller
{
    use StoresAttachments;

    public function store(Request $request, Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        $request->validate([
            'content' => 'required|string|max:5000',
            ...$this->attachmentRules(),
        ]);

        $comment = $ticket->comments()->create([
            'user_id' => auth()->id(),
            'content' => $request->content,
            'is_internal' => false,
        ]);

        // The replier has, by definition, seen everything up to now.
        $ticket->markViewedBy($request->user());

        $rejected = $this->storeAttachments($request, $comment, $request->user());

        return $this->withAttachmentWarning(
            back()->with('success', 'Comment added!'),
            $rejected,
        );
    }
}
