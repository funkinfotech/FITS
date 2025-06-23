<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    public function create()
    {
        return view('tickets.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'ticket_number' => 'required|unique:tickets,ticket_number',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        Ticket::create([
            'ticket_number' => $request->ticket_number,
            'name' => auth()->user()->name,
            'email' => auth()->user()->email,
            'priority' => 'medium',
            'status' => 'open',
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
