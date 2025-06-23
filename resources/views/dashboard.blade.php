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
                <div class="p-4">
                    <div class="flex justify-between">
                        <div>
                            <p class="font-semibold">{{ $ticket->subject }}</p>
                            <p class="text-sm text-gray-500">{{ $ticket->created_at->diffForHumans() }}</p>
                        </div>
                        <span class="px-3 py-1 text-sm font-medium rounded-full bg-gray-100 text-gray-800">
                            {{ ucfirst($ticket->status) }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
