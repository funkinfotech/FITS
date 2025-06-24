<?php

namespace App\Models;

use App\Enums\TicketStatus;
use App\Enums\TicketPriority;
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
        'priority' => TicketPriority::class,
    ];

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}