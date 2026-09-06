<?php

namespace App\Models;

use App\Enums\TicketStatus;
use App\Enums\TicketPriority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;

class Ticket extends Model
{

    protected static function booted()
    {
        static::creating(function ($ticket) {
            do {
                $number = mt_rand(10000000, 99999999);
            } while (self::where('ticket_number', $number)->exists());

            $ticket->ticket_number = $number;
        });

        // Comments cascade at the DB level (no Eloquent events), so clean up
        // every attached file here before the ticket goes.
        static::deleting(function (Ticket $ticket) {
            $ticket->attachments()->get()->each->delete();

            Attachment::query()
                ->where('attachable_type', Comment::class)
                ->whereIn('attachable_id', $ticket->comments()->pluck('id'))
                ->get()
                ->each
                ->delete();
        });
    }

    protected $fillable = [
        'name',
        'email',
        'priority',
        'status',
        'subject',
        'message',
        'ticket_number',
        'user_id',
        'assigned_to',
        'company_id',
        'contact_id',
        'inbound_message_id',
        'source',
    ];

    protected $casts = [
        'status' => TicketStatus::class,
        'priority' => TicketPriority::class,
        // Both populated by sub-selects on the customer dashboard.
        'last_activity_at' => 'datetime',
        'unread_count' => 'integer',
    ];

    public function comments()
    {
        //return $this->hasMany(Comment::class);
        return $this->hasMany(Comment::class)->oldest();
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest('id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Portal users who have opened this ticket, with when they last did. */
    public function viewers()
    {
        return $this->belongsToMany(User::class)->withPivot('last_viewed_at');
    }

    /**
     * Record that $user has just seen this ticket. Returns the previous
     * "last viewed" time (null if they'd never opened it), so callers can
     * highlight anything newer.
     */
    public function markViewedBy(User $user): ?Carbon
    {
        $previous = $this->viewers()
            ->where('users.id', $user->id)
            ->first()?->pivot?->last_viewed_at;

        $this->viewers()->syncWithoutDetaching([
            $user->id => ['last_viewed_at' => now()],
        ]);

        return $previous ? Carbon::parse($previous) : null;
    }

    /**
     * Add an `unread_count` sub-select: non-internal replies from someone other
     * than $user, created since $user last opened the ticket.
     */
    public function scopeWithUnreadCountFor(Builder $query, User $user): Builder
    {
        return $query->addSelect(['unread_count' => Comment::query()
            ->selectRaw('count(*)')
            ->whereColumn('comments.ticket_id', 'tickets.id')
            ->where('comments.is_internal', false)
            ->where(fn ($q) => $q->whereNull('comments.user_id')->orWhere('comments.user_id', '!=', $user->id))
            ->whereRaw(
                'comments.created_at > COALESCE(
                    (select last_viewed_at from ticket_user
                     where ticket_user.ticket_id = tickets.id and ticket_user.user_id = ?),
                    tickets.created_at
                )',
                [$user->id],
            )]);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}
