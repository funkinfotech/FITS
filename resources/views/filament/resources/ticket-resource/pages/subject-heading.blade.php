{{-- Inline-editable ticket subject. Rendered inside the page header's <h1>, so
     the root element must be phrasing content and inherits the heading font.
     At rest it is indistinguishable from the heading text; on focus a thin
     underline appears and the value commits on blur. --}}
<input
    x-data="{
        subject: @js($subject),
        original: @js($subject),
        commit() {
            const next = this.subject.trim();

            if (next === '') {
                this.subject = this.original;
                return;
            }

            this.subject = next;

            if (next === this.original) {
                return;
            }

            this.original = next;
            $wire.set('data.subject', next, false);
            $wire.autosave();
        },
    }"
    x-model="subject"
    :size="Math.min(Math.max(subject.length + 1, 8), 90)"
    @focus="$el.select()"
    @blur="commit()"
    @keydown.enter.prevent="$el.blur()"
    @keydown.escape.prevent="subject = original; $el.blur()"
    type="text"
    maxlength="255"
    aria-label="Ticket subject — edit and click away to save"
    title="Edit the subject; click away to save"
    class="fi-ticket-subject min-w-0 max-w-full appearance-none border-0 border-b border-transparent bg-transparent p-0 text-2xl font-bold tracking-tight text-gray-950 shadow-none outline-none ring-0 focus:border-gray-300 focus:outline-none focus:ring-0 dark:text-white dark:focus:border-white/20 sm:text-3xl"
/>
