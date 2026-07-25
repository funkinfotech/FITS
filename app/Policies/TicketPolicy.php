<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        return $this->belongsToTicket($user, $ticket);
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->belongsToTicket($user, $ticket);
    }

    protected function belongsToTicket(User $user, Ticket $ticket): bool
    {
        if ($ticket->user_id === $user->id) {
            return true;
        }

        return $user->company_id !== null && $ticket->company_id === $user->company_id;
    }
}
