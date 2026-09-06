<?php
namespace Tests\Feature;
use App\Models\{Comment,Company,Ticket,User};
use App\Support\AttachmentPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DashboardTest extends TestCase {
  use RefreshDatabase;

  protected function mk(User $u, string $status, string $subject, ?Company $co = null): Ticket {
    return Ticket::create(['name'=>$u->name,'email'=>$u->email,'priority'=>'Medium','status'=>$status,
      'subject'=>$subject,'message'=>'m','source'=>'portal','user_id'=>$u->id,'company_id'=>$co?->id]);
  }

  public function test_closed_tickets_are_hidden_by_default_and_reachable_via_tab(): void {
    $u = User::factory()->create(['is_admin'=>false]);
    $this->mk($u, 'Open', 'Active one');
    $this->mk($u, 'In Progress', 'Active two');
    $this->mk($u, 'Closed', 'Closed one');

    $active = $this->actingAs($u)->get(route('dashboard'));
    $active->assertOk()->assertSee('Active one')->assertSee('Active two')->assertDontSee('Closed one');
    $active->assertViewHas('counts', fn ($c) => $c['active'] === 2 && $c['closed'] === 1 && $c['all'] === 3);

    $closed = $this->actingAs($u)->get(route('dashboard', ['filter' => 'closed']));
    $closed->assertOk()->assertSee('Closed one')->assertDontSee('Active one');

    $all = $this->actingAs($u)->get(route('dashboard', ['filter' => 'all']));
    $all->assertSee('Active one')->assertSee('Closed one');
  }

  public function test_search_filters_by_subject_and_number(): void {
    $u = User::factory()->create(['is_admin'=>false]);
    $a = $this->mk($u, 'Open', 'Printer jam');
    $this->mk($u, 'Open', 'VPN issue');

    $this->actingAs($u)->get(route('dashboard', ['q' => 'printer']))
      ->assertSee('Printer jam')->assertDontSee('VPN issue');
    $this->actingAs($u)->get(route('dashboard', ['q' => $a->ticket_number]))
      ->assertSee('Printer jam')->assertDontSee('VPN issue');
  }

  public function test_list_is_ordered_by_latest_activity(): void {
    $u = User::factory()->create(['is_admin'=>false]);
    $old = $this->mk($u, 'Open', 'Older ticket');
    $new = $this->mk($u, 'Open', 'Newer ticket');
    // add a reply to the OLD ticket -> it should jump to the top
    $c = Comment::create(['ticket_id'=>$old->id,'user_id'=>$u->id,'content'=>'bump','is_internal'=>false]);
    $c->forceFill(['created_at'=>now()->addMinute()])->save();

    $html = $this->actingAs($u)->get(route('dashboard'))->content();
    $this->assertLessThan(strpos($html, 'Newer ticket'), strpos($html, 'Older ticket'));
  }

  public function test_tab_counts_reflect_status(): void {
    $u = User::factory()->create(['is_admin'=>false]);
    $this->mk($u, 'Open', 'o1');
    $this->mk($u, 'In Progress', 'o2');
    $this->mk($u, 'Closed', 'c1');
    $this->mk($u, 'Closed', 'c2');

    $this->actingAs($u)->get(route('dashboard'))
      ->assertViewHas('counts', fn ($c) => $c['active'] === 2 && $c['closed'] === 2 && $c['all'] === 4);
  }

  public function test_pagination_kicks_in_past_15(): void {
    $u = User::factory()->create(['is_admin'=>false]);
    foreach (range(1, 18) as $i) $this->mk($u, 'Open', "Ticket {$i}");
    $r = $this->actingAs($u)->get(route('dashboard'));
    $r->assertViewHas('tickets', fn ($t) => $t->count() === 15 && $t->total() === 18);
  }
}
