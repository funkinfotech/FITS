<?php

namespace App\Jobs;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Comment;
use App\Models\Contact;
use App\Models\Ticket;
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
        $subject = $this->item['Subject'] ?? '(no subject)';
        $body = $this->item['ExtractedMarkdownMessage'] ?? $this->item['RawTextBody'] ?? '';

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

            Ticket::create([
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
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
