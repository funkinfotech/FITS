@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto pt-12 py-10 px-6 bg-white shadow-md rounded-lg">
    <h1 class="text-2xl font-bold mb-6 text-gray-900">🎫 Ticket #{{ $ticket->ticket_number }}</h1>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div>
            <p class="text-sm text-gray-600">Status</p>
            <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold
                @if($ticket->status === 'open') bg-green-100 text-green-800
                @elseif($ticket->status === 'in progress') bg-yellow-100 text-yellow-800
                @else bg-gray-200 text-gray-800 @endif">
                {{ ucfirst($ticket->status) }}
            </span>
        </div>

        <div>
            <p class="text-sm text-gray-600">Priority</p>
            <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold
                @if($ticket->priority === 'High') bg-red-100 text-red-800
                @elseif($ticket->priority === 'Medium') bg-yellow-100 text-yellow-800
                @else bg-blue-100 text-blue-800 @endif">
                {{ ucfirst($ticket->priority) }}
            </span>
        </div>

        <div>
            <p class="text-sm text-gray-600">Created At</p>
            <p class="text-gray-800">{{ $ticket->created_at->format('F j, Y g:i A') }}</p>
        </div>

        <div>
            <p class="text-sm text-gray-600">Last Updated</p>
            <p class="text-gray-800">{{ $ticket->updated_at->format('F j, Y g:i A') }}</p>
        </div>
    </div>

    <div class="mb-4">
        <p class="text-sm text-gray-600">Subject</p>
        <p class="text-lg font-medium text-gray-900">{{ $ticket->subject }}</p>
    </div>

    <div>
        <p class="text-sm text-gray-600 mb-1">Message</p>
        <div class="bg-gray-50 px-4 py-2 rounded border text-gray-700 whitespace-pre-line">
            {{ trim($ticket->message) }}
            <p></p>
        </div>
    </div>
</div>
@endsection
