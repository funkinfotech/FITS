<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

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
    ];

    protected $casts = [
        'status' => TicketStatus::class,
    ];

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    protected function priority(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => ucfirst(strtolower($value)),
        );
    }

    protected function status(): Attribute
    {
        return Attribute::make(
        set: function ($value) {
            logger()->debug("Setting STATUS: " . $value); // <-- Add this
            return ucwords(strtolower($value));
        }
    );

//        return Attribute::make(
//            set: fn ($value) => ucfirst(strtolower($value)),
//        );
    }

}