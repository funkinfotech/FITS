<?php

namespace Tests\Feature;

use App\Filament\Resources\TicketResource\Pages\EditTicket;
use App\Filament\Resources\TicketResource\RelationManagers\TicketCommentsRelationManager;
use App\Mail\TicketReplyMail;
use App\Models\Comment;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class InternalCommentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUpTicket(): array
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $company = Company::create(['name' => 'Acme']);
        $contact = Contact::create(['company_id' => $company->id, 'name' => 'K Dawg']);
        $contact->emails()->create(['email' => 'kdawg@acme.test', 'is_primary' => true]);

        $ticket = Ticket::create([
            'name' => 'K Dawg',
            'email' => 'kdawg@acme.test',
            'priority' => 'Medium',
            'status' => 'Open',
            'subject' => 'Subject',
            'message' => 'Message',
            'source' => 'email',
            'company_id' => $company->id,
            'contact_id' => $contact->id,
        ]);

        return [$ticket, $contact];
    }

    protected function manager(Ticket $ticket)
    {
        return Livewire::test(TicketCommentsRelationManager::class, [
            'ownerRecord' => $ticket,
            'pageClass' => EditTicket::class,
        ]);
    }

    public function test_an_internal_note_never_notifies_anyone(): void
    {
        Mail::fake();
        [$ticket, $contact] = $this->setUpTicket();

        $this->manager($ticket)
            ->callTableAction('create', data: [
                'content' => 'Just a note to self.',
                'is_internal' => true,
                // The recipients list defaults to the contact and would normally
                // be checked; it must be ignored for an internal note.
                'ticket_status' => 'In Progress',
            ])
            ->assertHasNoTableActionErrors();

        $comment = $ticket->comments()->latest('id')->first();

        $this->assertTrue($comment->is_internal);
        $this->assertCount(0, $comment->recipients()->get());
        Mail::assertNotQueued(TicketReplyMail::class);
    }

    public function test_a_public_reply_still_notifies_the_selected_contacts(): void
    {
        Mail::fake();
        [$ticket, $contact] = $this->setUpTicket();

        $this->manager($ticket)
            ->callTableAction('create', data: [
                'content' => 'Here is your answer.',
                'is_internal' => false,
                'recipients' => [$contact->id],
                'ticket_status' => 'In Progress',
            ])
            ->assertHasNoTableActionErrors();

        $comment = $ticket->comments()->latest('id')->first();

        $this->assertFalse($comment->is_internal);
        $this->assertEqualsCanonicalizing([$contact->id], $comment->recipients()->pluck('contacts.id')->all());
        Mail::assertQueued(TicketReplyMail::class);
    }

    public function test_toggling_a_reply_to_internal_on_edit_drops_its_recipients(): void
    {
        Mail::fake();
        [$ticket, $contact] = $this->setUpTicket();

        $comment = Comment::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'content' => 'Public for now',
            'is_internal' => false,
        ]);
        $comment->recipients()->attach($contact->id);

        $this->manager($ticket)
            ->callTableAction('edit', $comment, data: [
                'content' => 'Actually make this internal',
                'is_internal' => true,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertTrue($comment->fresh()->is_internal);
        $this->assertCount(0, $comment->recipients()->get());
    }

    public function test_the_card_does_not_claim_an_internal_note_notified_anyone(): void
    {
        [$ticket, $contact] = $this->setUpTicket();

        // Simulate the pre-fix bug: an internal comment with a stray pivot row.
        $comment = Comment::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'content' => 'internal',
            'is_internal' => true,
        ]);
        $comment->recipients()->attach($contact->id);

        $html = $this->manager($ticket)->html();

        $this->assertStringNotContainsString('Notified:', $html);
    }
}
