<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Ticket;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Support\StoresAttachments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    use StoresAttachments;

    public function create()
    {
        return view('tickets.create');
    }

    public function show(Request $request, Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        $ticket->load([
            'comments' => fn ($query) => $query->where('is_internal', false)->reorder('created_at', 'desc'),
            'comments.user',
            'comments.contact',
            'comments.attachments',
            'attachments',
        ]);

        // Stamp "seen" and remember what was new so the view can flag it.
        $lastViewedAt = $ticket->markViewedBy($request->user());

        return view('tickets.show', compact('ticket', 'lastViewedAt'));
    }

    public function guestShow(Ticket $ticket)
    {
        $ticket->load([
            'comments' => fn ($query) => $query->where('is_internal', false)->reorder('created_at', 'desc'),
            'comments.user',
            'comments.contact',
            'comments.attachments',
            'attachments',
        ]);

        return view('tickets.guest-show', compact('ticket'));
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
            ...$this->attachmentRules(),
        ]);

        $contactId = Auth::user()->company_id
            ? Contact::whereHas('emails', fn ($query) => $query->where('email', Auth::user()->email))
                ->where('company_id', Auth::user()->company_id)
                ->value('id')
            : null;

        $ticket = Ticket::create([
            'ticket_number' => $request->ticket_number,
            'name' => auth()->user()->name,
            'email' => auth()->user()->email,
            'priority' => TicketPriority::from($request->priority)->value,
            'status' => TicketStatus::Open->value,
            'subject' => $request->subject,
            'message' => $request->message,
            'user_id' => Auth::id(),
            'company_id' => Auth::user()->company_id,
            'contact_id' => $contactId,
        ]);

        $ticket->markViewedBy($request->user());

        $rejected = $this->storeAttachments($request, $ticket, Auth::user());

        return $this->withAttachmentWarning(
            redirect()->route('tickets.index')->with('success', 'Ticket submitted!'),
            $rejected,
        );
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        $visible = fn ($query) => $query->where(function ($query) use ($user) {
            $query->where('user_id', $user->id);

            if ($user->company_id) {
                $query->orWhere('company_id', $user->company_id);
            }
        });

        $active = [TicketStatus::Open->value, TicketStatus::InProgress->value];

        $counts = [
            'active' => Ticket::where($visible)->whereIn('status', $active)->count(),
            'closed' => Ticket::where($visible)->where('status', TicketStatus::Closed->value)->count(),
        ];
        $counts['all'] = $counts['active'] + $counts['closed'];

        $filter = in_array($request->query('filter'), ['active', 'closed', 'all'], true)
            ? $request->query('filter')
            : 'active';

        $search = trim((string) $request->query('q', ''));

        $lastActivity = \App\Models\Comment::query()
            ->selectRaw('max(created_at)')
            ->whereColumn('ticket_id', 'tickets.id')
            ->where('is_internal', false);

        $tickets = Ticket::where($visible)
            ->when($filter === 'active', fn ($q) => $q->whereIn('status', $active))
            ->when($filter === 'closed', fn ($q) => $q->where('status', TicketStatus::Closed->value))
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('ticket_number', 'like', "%{$search}%");
            }))
            ->with('user:id,name')
            ->withCount([
                'comments as replies_count' => fn ($q) => $q->where('is_internal', false),
                'attachments as attachments_count',
            ])
            ->addSelect('tickets.*')
            ->addSelect(['last_activity_at' => $lastActivity])
            ->withUnreadCountFor($user)
            ->orderByRaw('COALESCE(last_activity_at, tickets.updated_at) DESC')
            ->paginate(15)
            ->withQueryString();

        return view('dashboard', [
            'tickets' => $tickets,
            'counts' => $counts,
            'filter' => $filter,
            'search' => $search,
        ]);
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
