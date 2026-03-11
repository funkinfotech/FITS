@php
    $ticket = $this->getOwnerRecord();
    $comments = $ticket->comments()->with('user')->latest('created_at')->get()->reverse();
@endphp

<div class="space-y-4">
    @forelse ($comments as $comment)
        @php
            $isStaff = filled($comment->user_id);
            $authorName = $comment->user?->name ?? 'Guest';
        @endphp

        <div class="flex {{ $isStaff ? 'justify-end' : 'justify-start' }}">
            <div class="max-w-3xl w-full">
                <div class="mb-1 flex items-center gap-2 {{ $isStaff ? 'justify-end' : 'justify-start' }}">
                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ $authorName }}
                    </span>

                    @if($isStaff)
                        <span class="inline-flex items-center rounded-md bg-primary-600/10 px-2 py-1 text-xs font-medium text-primary-700 dark:text-primary-300">
                            Staff
                        </span>
                    @endif

                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $comment->created_at?->diffForHumans() }}
                    </span>
                </div>

                <div class="
                    rounded-2xl px-4 py-3 shadow-sm ring-1
                    {{ $isStaff
                        ? 'bg-primary-50 ring-primary-200 dark:bg-primary-950/30 dark:ring-primary-800'
                        : 'bg-white ring-gray-200 dark:bg-gray-900 dark:ring-gray-800'
                    }}
                ">
                    <div class="prose prose-sm dark:prose-invert max-w-none whitespace-pre-wrap">
                        {{ $comment->content }}
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-2xl border border-dashed border-gray-300 dark:border-gray-700 p-6 text-sm text-gray-500 dark:text-gray-400">
            No comments yet.
        </div>
    @endforelse
</div>