<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function create()
    {
        return view('tickets.create');
    }

    public function show(Ticket $ticket)
    {
        if ($ticket->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        return view('tickets.show', compact('ticket'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'ticket_number' => 'required|unique:tickets,ticket_number',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => ['required', Rule::in(array_column(TicketPriority::cases(), 'value'))],
        ]);

        Ticket::create([
            'ticket_number' => $request->ticket_number,
            'name' => auth()->user()->name,
            'email' => auth()->user()->email,
            'priority' => TicketPriority::from($request->priority)->value,
            'status' => TicketStatus::Open->value,
            'subject' => $request->subject,
            'message' => $request->message,
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('tickets.index')->with('success', 'Ticket submitted!');
    }

    public function index()
    {
        $tickets = Ticket::where('user_id', Auth::id())->latest()->get();

        return view('dashboard', compact('tickets'));
    }
}
