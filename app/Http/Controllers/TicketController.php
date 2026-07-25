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
        $this->authorize('view', $ticket);

        $ticket->load([
            'comments' => fn ($query) => $query->where('is_internal', false)->latest(),
            'comments.user',
        ]);

        return view('tickets.show', compact('ticket'));
    }

    public function update(Request $request, Ticket $ticket)
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ]);

        try {
            $ticket->update($validated);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'Something went wrong while saving your changes. Please try again.');
        }

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket updated successfully.');
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
            'company_id' => Auth::user()->company_id,
        ]);

        return redirect()->route('tickets.index')->with('success', 'Ticket submitted!');
    }

    public function index()
    {
        $user = Auth::user();

        $tickets = Ticket::where(function ($query) use ($user) {
                $query->where('user_id', $user->id);

                if ($user->company_id) {
                    $query->orWhere('company_id', $user->company_id);
                }
            })
            ->latest()
            ->get();

        return view('dashboard', compact('tickets'));
    }

    public function updatePriority(Request $request, Ticket $ticket)
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'priority' => ['required', 'in:' . implode(',', array_map(
                fn ($case) => $case->value,
                TicketPriority::cases()
            ))],
        ]);

        $ticket->update([
            'priority' => $validated['priority'],
        ]);

        return back()->with('success', 'Priority updated.');
    }
}
