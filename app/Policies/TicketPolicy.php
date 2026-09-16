<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TicketPolicy
{
    /**
     * Tickets of other users are reported as "not found" so IDs don't leak.
     */
    private function owns(User $user, Ticket $ticket): Response
    {
        return $user->id === $ticket->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function view(User $user, Ticket $ticket): Response
    {
        return $this->owns($user, $ticket);
    }

    public function update(User $user, Ticket $ticket): Response
    {
        return $this->owns($user, $ticket);
    }

    public function delete(User $user, Ticket $ticket): Response
    {
        return $this->owns($user, $ticket);
    }
}
