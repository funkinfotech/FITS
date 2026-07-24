@extends('layouts.app')

@php

    use App\Enums\TicketPriority;
    use App\Enums\TicketStatus;

    $priority = $ticket->priority instanceof TicketPriority
        ? $ticket->priority
        : TicketPriority::tryFrom($ticket->priority) ?? TicketPriority::Medium;

    $statusEmoji = $ticket->status->emoji();
    $hasTicketErrors = $errors->hasAny(['subject', 'message']);
@endphp

@section('content')

<div class="max-w-3xl mx-auto pt-12 py-10 px-6">

    <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">
        &larr; Back to dashboard
    </a>

    <div class="mt-4 bg-white shadow-md rounded-lg p-6 sm:p-8">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-sm text-gray-500">Ticket #{{ $ticket->ticket_number }}</p>
                <h1 class="text-2xl font-bold text-gray-900">{{ $priority->emoji() }} {{ $ticket->subject }}</h1>
                <p class="mt-1 text-xs text-gray-400">
                    Opened {{ $ticket->created_at->format('F j, Y g:i A') }} &middot; Updated {{ $ticket->updated_at->diffForHumans() }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold {{ $ticket->status->colorClass() }}">
                    {{ $statusEmoji }} {{ $ticket->status->value }}
                </span>

                <div x-data="{ editingPriority: false }">
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
                        <form method="POST" action="{{ route('tickets.update-priority', $ticket) }}">
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
            </div>
        </div>

        {{-- Problem description --}}
        <div class="mt-6" x-data="{ editing: {{ $hasTicketErrors ? 'true' : 'false' }} }">
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Problem</h2>

                <button type="button" x-show="!editing" @click="editing = true" class="text-sm font-medium text-primary-600 hover:underline">
                    Edit
                </button>
            </div>

            <template x-if="!editing">
                <div class="bg-gray-50 border rounded-lg px-4 py-3 text-gray-800 text-base whitespace-pre-line leading-relaxed">{{ trim($ticket->message) }}</div>
            </template>

            <template x-if="editing">
                <form method="POST" action="{{ route('tickets.update', $ticket) }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="subject" class="block text-sm font-medium text-gray-700">Subject</label>
                        <input
                            type="text" id="subject" name="subject" required
                            value="{{ old('subject', $ticket->subject) }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        <x-input-error :messages="$errors->get('subject')" class="mt-1" />
                    </div>

                    <div>
                        <label for="message" class="block text-sm font-medium text-gray-700">Message</label>
                        <textarea
                            id="message" name="message" rows="6" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >{{ old('message', $ticket->message) }}</textarea>
                        <x-input-error :messages="$errors->get('message')" class="mt-1" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Save Changes</x-primary-button>
                        <button type="button" @click="editing = false" class="text-sm text-gray-500 hover:text-gray-700">
                            Cancel
                        </button>
                    </div>
                </form>
            </template>
        </div>
    </div>

    {{-- Reply --}}
    <div class="mt-6 bg-white shadow-md rounded-lg p-6 sm:p-8">
        <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-3">Add a Reply</h3>
        <form method="POST" action="{{ route('comments.store', $ticket) }}">
            @csrf
            <textarea
                name="content" rows="3" required placeholder="Write a reply..."
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >{{ old('content') }}</textarea>
            <x-input-error :messages="$errors->get('content')" class="mt-1" />
            <x-primary-button class="mt-2">Submit</x-primary-button>
        </form>
    </div>

    {{-- Comments, newest first --}}
    <div class="mt-6">
        <h3 class="text-lg font-semibold mb-4">&#128172; Conversation</h3>

        @forelse ($ticket->comments as $comment)
            <div class="bg-white rounded-lg shadow-sm p-4 mb-4 border">
                <div class="flex items-center justify-between">
                    <div class="font-medium text-gray-800">
                        {{ $comment->user->name ?? 'Guest' }}
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
    </div>

</div>
@endsection
