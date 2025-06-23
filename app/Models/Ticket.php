<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}

namespace App\Models;

use Illuminate\Database\Eloquent\Model;