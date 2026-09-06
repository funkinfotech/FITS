<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket #{{ $ticket->ticket_number }} - {{ config('app.name', 'FunkIT HelpDesk') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-100">

@php
    use App\Enums\TicketPriority;

    $priority = $ticket->priority instanceof TicketPriority
        ? $ticket->priority
        : TicketPriority::tryFrom($ticket->priority) ?? TicketPriority::Medium;

@endphp

<div class="max-w-3xl mx-auto pt-12 py-10 px-6">

    <a href="/" class="inline-flex items-center gap-2 mb-6 text-gray-800 hover:text-gray-900">
        <img src="{{ asset('images/funkit-logo.png') }}" alt="FunkIT" class="w-8 h-8">
        <span class="font-semibold">FunkIT HelpDesk</span>
    </a>

    <div class="bg-white shadow-md rounded-lg p-6 sm:p-8">
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

                <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold {{ $priority->colorClass() }}">
                    {{ $priority->value }}
                </span>
            </div>
        </div>

    </div>

    <div class="mt-6">
        <h3 class="text-lg font-semibold mb-4">&#128172; Conversation</h3>

        @foreach ($ticket->comments as $comment)
            <div class="bg-white rounded-lg shadow-sm p-4 mb-4 border">
                <div class="flex items-center justify-between">
                    <div class="font-medium text-gray-800">
                        {{ $comment->user->name ?? $comment->contact->name ?? 'Guest' }}
                    </div>
                    <div class="text-sm text-gray-500">
                        {{ $comment->created_at->diffForHumans() }}
                    </div>
                </div>
                <div class="mt-2 text-gray-700 whitespace-pre-line">
                    {{ $comment->content }}
                </div>
                <x-attachments :items="$comment->attachments" :guest-ticket="$ticket" />
            </div>
        @endforeach

        {{-- Original message — start of the thread --}}
        <div class="rounded-lg border border-l-4 border-gray-200 border-l-primary-300 bg-gray-50 p-4">
            <div class="flex items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium text-gray-800">{{ $ticket->name ?: 'Customer' }}</span>
                    <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-semibold text-gray-600">Original message</span>
                </div>
                <span class="shrink-0 text-sm text-gray-500" title="{{ $ticket->created_at->inDisplayTz()->format('F j, Y g:i A') }}">
                    {{ $ticket->created_at->diffForHumans() }}
                </span>
            </div>
            <div class="mt-2 text-gray-800 whitespace-pre-line leading-relaxed">{{ trim($ticket->message) }}</div>
            <x-attachments :items="$ticket->attachments" :guest-ticket="$ticket" />
        </div>
    </div>

    <p class="mt-4 text-sm text-gray-500">
        To reply, respond directly to the email you received about this ticket.
    </p>

</div>
</body>
</html>
