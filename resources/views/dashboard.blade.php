@extends('layouts.app')

@php
    use App\Enums\TicketStatus;
    use App\Enums\TicketPriority;

    $tabs = ['active' => 'Active', 'closed' => 'Closed', 'all' => 'All'];
@endphp

@section('content')
<div class="max-w-4xl mx-auto py-10 px-6">
    <h1 class="text-3xl font-bold mb-6 text-primary">Welcome back, {{ Auth::user()->name }} 👋</h1>

    <div class="mb-8">
        <a href="{{ route('tickets.create') }}"
           class="inline-block px-5 py-3 bg-primary text-white font-semibold rounded shadow hover:bg-opacity-90">
            Submit a New Ticket
        </a>
    </div>

    <h2 class="text-xl font-semibold mb-4">Your Tickets</h2>

    {{-- Filter tabs --}}
    <div class="mb-4 flex flex-wrap gap-1 border-b border-gray-200">
        @foreach ($tabs as $key => $label)
            @php $count = $key === 'all' ? $counts['all'] : ($counts[$key] ?? 0); @endphp
            <a href="{{ route('dashboard', $key === 'active' ? [] : ['filter' => $key]) }}"
               @class([
                   '-mb-px inline-flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium transition',
                   'border-primary text-primary' => $filter === $key,
                   'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' => $filter !== $key,
               ])>
                {{ $label }}
                <span @class([
                    'rounded-full px-1.5 py-0.5 text-xs font-semibold',
                    'bg-primary text-white' => $filter === $key,
                    'bg-gray-100 text-gray-500' => $filter !== $key,
                ])>{{ $count }}</span>
            </a>
        @endforeach
    </div>

    @if ($tickets->isEmpty())
        @if ($filter === 'closed')
            <p class="text-gray-600">No closed tickets yet.</p>
        @elseif ($filter === 'active' && $counts['closed'] > 0)
            <p class="text-gray-600">
                You have no active tickets. 🎉
                <a href="{{ route('dashboard', ['filter' => 'closed']) }}" class="font-medium text-primary hover:underline">
                    View {{ $counts['closed'] }} closed {{ Str::plural('ticket', $counts['closed']) }}
                </a>
            </p>
        @else
            <p class="text-gray-600">You haven't submitted any tickets yet.</p>
        @endif
    @else
        <div class="bg-white shadow rounded divide-y">
            @foreach ($tickets as $ticket)
                @php
                    $status = $ticket->status instanceof TicketStatus
                        ? $ticket->status
                        : TicketStatus::tryFrom($ticket->status) ?? TicketStatus::Open;

                    $priority = $ticket->priority instanceof TicketPriority
                        ? $ticket->priority
                        : TicketPriority::tryFrom($ticket->priority) ?? TicketPriority::Medium;

                    $unread = (int) ($ticket->unread_count ?? 0) > 0;
                @endphp
                <a href="{{ route('tickets.show', $ticket) }}" class="block hover:bg-gray-50 transition rounded-md">
                    <div class="p-4 border-b {{ $unread ? 'bg-primary-50' : '' }}">
                        <div class="flex justify-between items-center gap-3">
                            <div class="min-w-0">
                                <h2 class="text-lg font-semibold text-gray-900 flex flex-wrap items-center gap-x-2">
                                    <span>{{ $priority->value === 'High' ? '🔥' : '🎫' }} Ticket #{{ $ticket->ticket_number }} — {{ $ticket->subject }}</span>
                                    @if ($unread)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-white">
                                            <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                                            {{ $ticket->unread_count > 1 ? $ticket->unread_count . ' new replies' : 'New reply' }}
                                        </span>
                                    @endif
                                </h2>
                                <p class="text-sm text-gray-600">{{ $ticket->created_at->inDisplayTz()->format('F j, Y g:i A') }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-1 shrink-0">
                                <span class="inline-block rounded-md px-2 py-1 text-xs font-semibold {{ $status->colorClass() }}">
                                    {{ $status->value }}
                                </span>
                                <span class="inline-flex justify-between items-center gap-2 px-3 py-0.5 rounded-md text-xs font-semibold {{ $priority->colorClass() }}">
                                    {{ $priority->value }}
                                </span>
                            </div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        @if ($tickets->hasPages())
            <div class="mt-6">{{ $tickets->links() }}</div>
        @endif
    @endif
</div>
@endsection
