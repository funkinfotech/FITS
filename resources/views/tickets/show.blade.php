@extends('layouts.app')

@php

    use App\Enums\TicketPriority;
    use App\Enums\TicketStatus;

    $priority = $ticket->priority instanceof TicketPriority
        ? $ticket->priority
        : TicketPriority::tryFrom($ticket->priority) ?? TicketPriority::Medium;

    $priorityEmoji = $ticket->priority->emoji();
    $statusEmoji = $ticket->status->emoji();
@endphp

@section('content')

<div class="max-w-3xl mx-auto pt-12 py-10 px-6 bg-white shadow-md rounded-lg">
    <h1 class="text-2xl font-bold mb-6 text-gray-900">
        {{ $priority->value === 'High' ? '🔥' : '🎫' }} Ticket #{{ $ticket->ticket_number }} — {{ $ticket->subject }}</h1>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div>
            <p class="text-sm text-gray-600">Status</p>
            <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold {{ $ticket->status->colorClass() }}">
                {{ $statusEmoji }} {{ $ticket->status->value }}
            </span>
        </div>

        <div x-data="{ editingPriority: false }">
            <p class="text-sm text-gray-600">Priority</p>

            <template x-if="!editingPriority">
                <button
                    type="button"
                    @click="editingPriority = true"
                    class="inline-block px-3 py-1 rounded-full text-sm font-semibold {{ $priority->colorClass() }}"
                >
                    {{ $priority->emoji() }} {{ $priority->value }}
                </button>
            </template>

            <template x-if="editingPriority">
                <form method="POST" action="{{ route('tickets.update-priority', $ticket) }}" class="mt-1">
                    @csrf
                    @method('PATCH')

                    <select
                        name="priority"
                        x-init="$el.focus()"
                        @change="$el.form.submit()"
                        @blur="editingPriority = false"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        @foreach (TicketPriority::cases() as $priorityOption)
                            <option
                                value="{{ $priorityOption->value }}"
                                @selected($priority->value === $priorityOption->value)
                            >
                                {{ $priorityOption->emoji() }} {{ $priorityOption->value }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </template>
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

    <h3 class="text-xl font-semibold mt-10 mb-4">💬 Comments</h3>
    @forelse ($ticket->comments as $comment)
        <div class="bg-white rounded-lg shadow-sm p-4 mb-4 border">
            <div class="flex items-center justify-between">
                <div class="font-medium text-gray-800">
                    {{ $comment->user->name }}
                </div>
                <div class="text-sm text-gray-500">
                    {{ $comment->created_at->diffForHumans() }}
                </div>
            </div>
            <div class="mt-2 text-gray-700 whitespace-pre-line">
                {{ $comment->content }}
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500">No comments yet.</p>
    @endforelse

    <h3 class="font-semibold mt-6">Add a Comment</h3>
    <form method="POST" action="{{ route('comments.store', $ticket) }}">
        @csrf
        <textarea name="content" class="w-full border rounded p-2" rows="3" required></textarea>
        <x-primary-button class="mt-2">Submit</x-primary-button>
    </form>

</div>
@endsection
