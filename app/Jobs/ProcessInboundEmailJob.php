<?php

namespace App\Jobs;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Comment;
use App\Models\Contact;
use App\Models\Ticket;
use App\Support\TicketMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessInboundEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(public array $item)
    {
    }

    public function handle(): void
    {
        $messageId = $this->item['MessageId'] ?? null;

        if ($messageId && (
            Ticket::where('inbound_message_id', $messageId)->exists()
            || Comment::where('inbound_message_id', $messageId)->exists()
        )) {
            return;
        }

        $fromAddress = $this->item['From']['Address'] ?? null;
        $fromName = $this->item['From']['Name'] ?? null;

        if (! $fromAddress) {
            Log::warning('Inbound email missing From address, skipping.', ['item' => $this->item]);

            return;
        }

        $toAddress = $this->item['To'][0]['Address'] ?? '';
        $subject = html_entity_decode($this->item['Subject'] ?? '(no subject)', ENT_QUOTES | ENT_HTML5);
        $body = $this->extractBody();

        $contact = Contact::whereHas('emails', fn ($query) => $query->where('email', $fromAddress))->first();

        try {
            if (preg_match('/helpdesk\+(\d+)@/i', $toAddress, $matches)) {
                $ticket = Ticket::where('ticket_number', $matches[1])->first();

                if ($ticket) {
                    $ticket->comments()->create([
                        'user_id' => null,
                        'contact_id' => $contact?->id,
                        'content' => $body,
                        'is_internal' => false,
                        'inbound_message_id' => $messageId,
                    ]);

                    return;
                }
            }

            $ticket = Ticket::create([
                'name' => $fromName ?: $fromAddress,
                'email' => $fromAddress,
                'priority' => TicketPriority::Medium->value,
                'status' => TicketStatus::Open->value,
                'subject' => $subject,
                'message' => $body,
                'user_id' => null,
                'company_id' => $contact?->company_id,
                'contact_id' => $contact?->id,
                'inbound_message_id' => $messageId,
                'source' => 'email',
            ]);

            if ($contact) {
                TicketMailer::sendAutoReply($ticket);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function extractBody(): string
    {
        $markdown = trim((string) ($this->item['ExtractedMarkdownMessage'] ?? ''));

        if ($markdown !== '' && ! $this->looksLikeHtml($markdown)) {
            return html_entity_decode($markdown, ENT_QUOTES | ENT_HTML5);
        }

        $text = trim((string) ($this->item['RawTextBody'] ?? ''));

        if ($text !== '' && ! $this->looksLikeHtml($text)) {
            return html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        }

        $html = $this->item['RawHtmlBody'] ?? $markdown ?: $text;

        return $this->htmlToPlainText((string) $html);
    }

    protected function looksLikeHtml(string $value): bool
    {
        return (bool) preg_match('/<\s*(html|body|head|meta|div|p|br|span|table)\b/i', $value);
    }

    protected function htmlToPlainText(string $html): string
    {
        $html = preg_replace('/<(head|style|script)\b[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
        $html = preg_replace('/<\s*(br|\/p|\/div|\/tr|\/li)\s*\/?>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
