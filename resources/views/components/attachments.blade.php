@props(['items', 'guestTicket' => null])

@php
    use Illuminate\Support\Facades\URL;

    $items = collect($items);

    $urlFor = fn ($attachment, bool $download = false) => $guestTicket
        ? URL::signedRoute('attachments.guest-view', array_filter([
            'ticket' => $guestTicket,
            'attachment' => $attachment,
            'download' => $download ? 1 : null,
        ]))
        : $attachment->url() . ($download ? '?download' : '');
@endphp

@if ($items->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'mt-3 flex flex-wrap gap-2']) }}>
        @foreach ($items as $attachment)
            @if ($attachment->isImage())
                <a
                    href="{{ $urlFor($attachment) }}"
                    target="_blank"
                    rel="noopener"
                    title="{{ $attachment->original_name }} ({{ $attachment->humanSize() }})"
                    class="block h-20 w-20 overflow-hidden rounded-lg border border-gray-200 bg-gray-50 hover:border-gray-300"
                >
                    <img
                        src="{{ $urlFor($attachment) }}"
                        alt="{{ $attachment->original_name }}"
                        loading="lazy"
                        class="h-full w-full object-cover"
                    >
                </a>
            @else
                <a
                    href="{{ $urlFor($attachment, download: true) }}"
                    class="inline-flex max-w-[16rem] items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 hover:border-gray-300 hover:bg-gray-50"
                >
                    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <span class="truncate">{{ $attachment->original_name }}</span>
                    <span class="shrink-0 text-xs text-gray-400">{{ $attachment->humanSize() }}</span>
                </a>
            @endif
        @endforeach
    </div>
@endif
