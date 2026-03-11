@php
    $comments = $record->comments()->with('user')->orderBy('created_at')->get();
@endphp

<div class="space-y-6">
    @forelse ($comments as $comment)
        @php
            $isStaff = filled($comment->user_id);
            $authorName = $comment->user?->name ?? $record->name ?? 'Guest';
        @endphp

        <div class="flex {{ $isStaff ? 'justify-end' : 'justify-start' }}">
            <div class="w-full max-w-3xl">
                <div class="mb-2 flex items-center gap-2 text-sm {{ $isStaff ? 'justify-end' : 'justify-start' }}">
                    <span class="font-semibold text-gray-950 dark:text-white">
                        {{ $authorName }}
                    </span>

                    @if ($isStaff)
                        <span class="rounded-md bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-200 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/20">
                            Staff
                        </span>
                    @endif

                    <span class="text-gray-500 dark:text-gray-400">
                        {{ $comment->created_at?->diffForHumans() }}
                    </span>
                </div>

                <div class="
                    rounded-2xl px-4 py-3 text-sm shadow-sm ring-1 ring-inset
                    {{ $isStaff
                        ? 'bg-primary-50 text-gray-900 ring-primary-200 dark:bg-primary-500/10 dark:text-white dark:ring-primary-500/20'
                        : 'bg-white text-gray-900 ring-gray-200 dark:bg-gray-900 dark:text-white dark:ring-white/10'
                    }}
                ">
                    <div class="whitespace-pre-wrap break-words">
                        {{ $comment->content }}
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-2xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
            No comments yet.
        </div>
    @endforelse
</div>