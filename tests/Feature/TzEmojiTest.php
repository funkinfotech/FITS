<?php
namespace Tests\Feature;
use App\Models\{Comment,Ticket,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TzEmojiTest extends TestCase {
  use RefreshDatabase;

  public function test_display_tz_macro_shifts_utc_to_eastern(): void {
    config(['app.display_timezone' => 'America/New_York']);
    // 2026-01-15 18:00 UTC  ->  13:00 EST
    $utc = Carbon::create(2026, 1, 15, 18, 0, 0, 'UTC');
    $this->assertSame('Jan 15, 2026 1:00 PM', $utc->inDisplayTz()->format('M j, Y g:i A'));
    // summer DST: 2026-07-15 18:00 UTC -> 14:00 EDT
    $utc2 = Carbon::create(2026, 7, 15, 18, 0, 0, 'UTC');
    $this->assertSame('Jul 15, 2026 2:00 PM', $utc2->inDisplayTz()->format('M j, Y g:i A'));
  }

  protected function ticket(User $u): Ticket {
    $t = Ticket::create(['name'=>'Kev','email'=>$u->email,'priority'=>'High','status'=>'In Progress',
      'subject'=>'Printer down','message'=>'m','source'=>'portal','user_id'=>$u->id]);
    $t->forceFill(['created_at' => Carbon::create(2026, 3, 10, 15, 30, 0, 'UTC')])->save();
    return $t;
  }

  public function test_portal_show_has_no_status_priority_or_subject_emoji_and_eastern_times(): void {
    $u = User::factory()->create(['is_admin'=>false]);
    $t = $this->ticket($u);
    $html = $this->actingAs($u)->get(route('tickets.show', $t))->assertOk()->content();

    // status/priority/subject emojis gone
    foreach (['⏳','🔥','🧊','💧','💤','🆕'] as $e) {
      $this->assertStringNotContainsString($e, $html, "emoji {$e} should be gone from the portal show page");
    }
    $this->assertStringContainsString('>Printer down</h1>', $html); // subject bare

    // 15:30 UTC on Mar 10 2026 -> 11:30 AM EDT (DST started Mar 8 2026)
    $this->assertStringContainsString('March 10, 2026 11:30 AM', $html);
  }

  public function test_dashboard_keeps_the_ticket_and_fire_emoji_on_the_card_title(): void {
    $u = User::factory()->create(['is_admin'=>false]);
    $high = $this->ticket($u);
    $med = Ticket::create(['name'=>'Kev','email'=>$u->email,'priority'=>'Medium','status'=>'Open',
      'subject'=>'VPN slow','message'=>'m','source'=>'portal','user_id'=>$u->id]);

    $html = $this->actingAs($u)->get(route('dashboard'))->assertOk()->content();
    $this->assertStringContainsString('🔥 Ticket #' . $high->ticket_number, $html);
    $this->assertStringContainsString('🎫 Ticket #' . $med->ticket_number, $html);
    // but not the status/priority badge emojis
    $this->assertStringNotContainsString('⏳', $html);
  }

  public function test_guest_show_also_stripped(): void {
    $u = User::factory()->create(['is_admin'=>false]);
    $t = $this->ticket($u);
    $html = $this->get(URL::signedRoute('tickets.guest-view', ['ticket' => $t]))->assertOk()->content();
    foreach (['⏳','🔥','💤'] as $e) $this->assertStringNotContainsString($e, $html);
    $this->assertStringContainsString('March 10, 2026 11:30 AM', $html);
  }
}
