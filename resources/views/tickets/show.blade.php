@extends('layouts.app')

@php
    use App\Enums\TicketPriority;
    use App\Enums\TicketStatus;

    $priority = $ticket->priority instanceof TicketPriority
        ? $ticket->priority
        : TicketPriority::tryFrom($ticket->priority) ?? TicketPriority::Medium;

    $hasTicketErrors = $errors->hasAny(['subject', 'message']);
@endphp

@section('content')

<div class="max-w-3xl mx-auto pt-12 py-10 px-6">

    <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">
        &larr; Back to dashboard
    </a>

    {{-- Ticket header --}}
    <div class="mt-4 bg-white shadow-md rounded-lg p-6 sm:p-8">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-sm text-gray-500">Ticket #{{ $ticket->ticket_number }}</p>
                <h1 class="text-2xl font-bold text-gray-900">{{ $ticket->subject }}</h1>
                <p class="mt-1 text-xs text-gray-400">
                    Opened {{ $ticket->created_at->inDisplayTz()->format('F j, Y g:i A') }} &middot; Updated {{ $ticket->updated_at->diffForHumans() }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold {{ $ticket->status->colorClass() }}">
                    {{ $ticket->status->value }}
                </span>

                <div x-data="{ editingPriority: false }">
                    <template x-if="!editingPriority">
                        <button
                            type="button"
                            @click="editingPriority = true"
                            class="inline-block px-3 py-1 rounded-full text-sm font-semibold {{ $priority->colorClass() }}"
                        >
                            {{ $priority->value }}
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
                                        {{ $priorityOption->value }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- Reply — right under the header, above the thread --}}
    <div class="mt-6 bg-white shadow-md rounded-lg p-6 sm:p-8">
        <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-3">Add a Reply</h3>
        <form method="POST" action="{{ route('comments.store', $ticket) }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <div>
                <textarea
                    name="content" rows="3" required placeholder="Write a reply..."
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >{{ old('content') }}</textarea>
                <x-input-error :messages="$errors->get('content')" class="mt-1" />
            </div>

            <x-attachment-input />

            <x-primary-button>Submit</x-primary-button>
        </form>
    </div>

    {{-- Conversation: newest first, original message last --}}
    <div class="mt-6">
        <h3 class="text-lg font-semibold mb-4">&#128172; Conversation</h3>

        @foreach ($ticket->comments as $comment)
            @php
                $isNew = isset($lastViewedAt)
                    && $lastViewedAt !== null
                    && $comment->created_at->gt($lastViewedAt)
                    && $comment->user_id !== Auth::id();
            @endphp
            <div @class([
                'rounded-lg shadow-sm p-4 mb-4 border',
                'bg-white' => ! $isNew,
                'bg-primary-50 border-primary-200' => $isNew,
            ])>
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-gray-800">
                            {{ $comment->user->name ?? $comment->contact->name ?? 'Guest' }}
                        </span>
                        @if ($isNew)
                            <span class="rounded-full bg-primary-600 px-2 py-0.5 text-xs font-semibold text-white">New</span>
                        @endif
                    </div>
                    <div class="shrink-0 text-sm text-gray-500" title="{{ $comment->created_at->inDisplayTz()->format('F j, Y g:i A') }}">
                        {{ $comment->created_at->diffForHumans() }}
                    </div>
                </div>
                <div class="mt-2 text-gray-700 whitespace-pre-line">
                    {{ $comment->content }}
                </div>
                <x-attachments :items="$comment->attachments" />
            </div>
        @endforeach

        {{-- The original message — start of the thread --}}
        <div
            class="rounded-lg border border-l-4 border-gray-200 border-l-primary-300 bg-gray-50 p-4"
            x-data="{ editing: {{ $hasTicketErrors ? 'true' : 'false' }} }"
        >
            <div class="flex items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium text-gray-800">{{ $ticket->name ?: 'You' }}</span>
                    <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-semibold text-gray-600">Original message</span>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <span class="text-sm text-gray-500" title="{{ $ticket->created_at->inDisplayTz()->format('F j, Y g:i A') }}">
                        {{ $ticket->created_at->diffForHumans() }}
                    </span>
                    <button type="button" x-show="!editing" @click="editing = true" class="text-xs font-medium text-primary-600 hover:underline">
                        Edit
                    </button>
                </div>
            </div>

            <template x-if="!editing">
                <div>
                    <div class="mt-2 text-gray-800 whitespace-pre-line leading-relaxed">{{ trim($ticket->message) }}</div>
                    <x-attachments :items="$ticket->attachments" />
                </div>
            </template>

            <template x-if="editing">
                <form method="POST" action="{{ route('tickets.update', $ticket) }}" class="mt-3 space-y-4">
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

</div>
@endsection
