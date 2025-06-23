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
                    $priority = strtolower(trim($ticket->priority));
                    $status = strtolower(trim($ticket->status));

                    $statusClass = match ($status) {
                        'open' => 'bg-green-100 text-green-800',
                        'in progress' => 'bg-yellow-100 text-yellow-800',
                        'closed' => 'bg-gray-300 text-gray-800',
                        default => 'bg-gray-100 text-gray-700',
                    };

                    $priorityClass = match ($priority) {
                        'low' => 'bg-blue-100 text-blue-800',
                        'medium' => 'bg-yellow-100 text-yellow-800',
                        'high' => 'bg-red-100 text-red-800',
                        default => 'bg-gray-100 text-gray-700',
                    };

                    $statusEmoji = match ($status) {
                        'open' => '🟢',
                        'in progress' => '🟡',
                        'closed' => '⚪',
                        default => '🔄',
                    };

                    $priorityEmoji = match ($priority) {
                        'low' => '🧊',
                        'medium' => '🟠',
                        'high' => '🔥',
                        default => '❔',
                    };
                @endphp
                <a href="{{ route('tickets.show', $ticket) }}" class="block hover:bg-gray-50 transition rounded-lg">
                    <div class="bg-white shadow rounded-lg p-4 mb-4 border border-gray-200">
                        <div class="flex justify-between items-start">
                            {{-- Left Side --}}
                            <div>
                                <h2 class="text-lg font-semibold text-gray-800">
                                    {{ $ticket->subject }}
                                </h2>
                                <p class="text-sm text-gray-500 mt-1">
                                    Submitted {{ $ticket->created_at->diffForHumans() }}
                                </p>
                            </div>

                            {{-- Right Side --}}
                            <div class="flex flex-col items-end gap-1">
                                {{-- Status Badge --}}
                                <span class="inline-flex justify-between items-center gap-2 px-3 py-0.5 rounded-full text-xs font-semibold {{ $statusClass }}">
                                    {{ ucfirst($ticket->status) }}
                                    <span>{{ $statusEmoji }}</span>
                                </span>

                                {{-- Priority Badge --}}
                                <span class="inline-flex justify-between items-center gap-2 px-3 py-0.5 rounded-full text-xs font-semibold {{ $priorityClass }}">
                                    {{ ucfirst($ticket->priority) }}
                                    <span>{{ $priorityEmoji }}</span>
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
