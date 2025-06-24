@extends('layouts.app') {{-- Adjust to your actual layout if needed --}}

@php
    use App\Enums\TicketStatus;
    use App\Enums\TicketPriority;
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

    @if ($tickets->isEmpty())
        <p class="text-gray-600">You haven't submitted any tickets yet.</p>
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
                @endphp
                <a href="{{ route('tickets.show', $ticket) }}" class="block hover:bg-gray-50 transition rounded-lg">
                    <div class="p-4 border-b">
                        <div class="flex justify-between items-center">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">
                                    {{ $priority->emoji() }}
                                     Ticket 🎫 #{{ $ticket->ticket_number }} — {{ $ticket->subject }}
                                </h2>
                                <p class="text-sm text-gray-600">{{ $ticket->created_at->format('F j, Y g:i A') }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-1">

                                <span class="inline-block rounded px-2 py-1 text-xs font-semibold {{ $status->colorClass() }}">
                                    {{ $status->value }} {{ $status->emoji() }}
                                </span>

                                <span class="inline-flex justify-between items-center gap-2 px-3 py-0.5 rounded-full text-xs font-semibold {{ $priority->colorClass() }}">
                                    {{ $priority->value }}
                                </span>
                            </div>
                        </div>
                    </div>  
                </a>              
            @endforeach

        </div>
    @endif
</div>
@endsection
