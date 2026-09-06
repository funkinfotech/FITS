{{--
    One entry in the ticket conversation. The customer's original message is
    appended as the last (oldest) entry — see TicketCommentsRelationManager.
    Who wrote it is the bordered sign-off in the bottom-right.
--}}
@php
    $entry = $getRecord();

    $isOriginal = (bool) ($entry->is_original_message ?? false);
    $isInternal = ! $isOriginal && (bool) $entry->is_internal;
    $isStaff = ! $isOriginal && filled($entry->user_id);

    if ($isOriginal) {
        $label = 'Original message';
        $icon = 'heroicon-m-inbox-arrow-down';
        $author = $entry->author_name;
        $signoff = $entry->author_email;
    } elseif ($isInternal) {
        $label = 'Internal note';
        $icon = 'heroicon-m-lock-closed';
        $author = $entry->user?->name ?? 'Staff';
        $signoff = 'Internal note';
    } else {
        $label = 'Reply';
        $icon = 'heroicon-m-chat-bubble-left-right';
        $author = $entry->user?->name ?? $entry->contact?->name ?? 'Guest';
        $signoff = $isStaff
            ? $entry->user?->email
            : ($entry->contact?->emails->firstWhere('is_primary', true)?->email
                ?? $entry->contact?->emails->first()?->email
                ?? 'Customer');
    }

    // Internal notes and the original message never notify anyone.
    $notified = ($isOriginal || $isInternal)
        ? collect()
        : $entry->recipients->pluck('name')->filter()->values();

    $attachments = $entry->relationLoaded('attachments') ? $entry->attachments : collect();
@endphp

<article @class([
    'ticket-message ticket-comment',
    'ticket-comment--internal' => $isInternal,
    'ticket-comment--original' => $isOriginal,
])>
    <header class="ticket-message__header">
        <x-filament::icon :icon="$icon" class="ticket-message__header-icon" />
        <span>{{ $label }}</span>
        <span class="ticket-comment__time" title="{{ $entry->created_at?->inDisplayTz()->format('D, M j, Y g:i A') }}">
            {{ $entry->created_at?->diffForHumans() }}
        </span>
    </header>

    <div class="ticket-message__body">{{ $entry->content }}</div>

    @if ($attachments->isNotEmpty())
        <div class="ticket-att">
            @foreach ($attachments as $att)
                @if ($att->isImage())
                    <a
                        class="ticket-att__thumb"
                        href="{{ route('attachments.show', $att) }}"
                        target="_blank"
                        rel="noopener"
                        title="{{ $att->original_name }} ({{ $att->humanSize() }})"
                    >
                        <img src="{{ route('attachments.show', $att) }}" alt="{{ $att->original_name }}" loading="lazy">
                    </a>
                @else
                    <a class="ticket-att__file" href="{{ route('attachments.show', $att) }}?download">
                        <span class="ticket-att__file-name">{{ $att->original_name }}</span>
                        <span class="ticket-att__file-size">{{ $att->humanSize() }}</span>
                    </a>
                @endif
            @endforeach
        </div>
    @endif

    @if ($notified->isNotEmpty())
        <div class="ticket-comment__notified">Notified: {{ $notified->join(', ') }}</div>
    @endif

    <footer class="ticket-message__signoff">
        <div class="ticket-message__signoff-card">
            <span class="ticket-message__signoff-name">— {{ $author }}</span>
            @if (filled($signoff))
                <span class="ticket-message__signoff-email">{{ $signoff }}</span>
            @endif
        </div>
    </footer>
</article>
