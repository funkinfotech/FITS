@extends('layouts.app') {{-- Adjust to your actual layout if needed --}}

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
                    $statusEmoji = $ticket->status->emoji();
                    $priorityEmoji = $ticket->priority->emoji();
                    $statusClass = $ticket->status->colorClass();
                    $priorityClass = $ticket->priority->colorClass();
                @endphp
                <a href="{{ route('tickets.show', $ticket) }}" class="block hover:bg-gray-50 transition rounded-lg">
                    <div class="p-4 border-b">
                        <div class="flex justify-between items-center">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">
                                    🎫 Ticket #{{ $ticket->ticket_number }} — {{ $ticket->subject }}
                                </h2>
                                <p class="text-sm text-gray-600">{{ $ticket->created_at->format('F j, Y g:i A') }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <span class="inline-flex items-center gap-2 px-3 py-0.5 rounded-full text-xs font-semibold {{ $statusClass }}">
                                    {{ $statusEmoji }} {{ $ticket->status->value }}
                                </span>
                                <span class="inline-flex items-center gap-2 px-3 py-0.5 rounded-full text-xs font-semibold {{ $priorityClass }}">
                                    {{ $priorityEmoji }} {{ $ticket->priority->value }}
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
