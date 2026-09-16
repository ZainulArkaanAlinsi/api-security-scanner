<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class TicketShareController extends Controller
{
    /**
     * Publish or unpublish a read-only report. Turning sharing off and on again
     * issues a new token, so an old link stops working.
     */
    public function update(Request $request, Ticket $ticket)
    {
        Gate::authorize('update', $ticket);

        $enabled = $request->boolean('share');

        $ticket->update(['share_token' => $enabled ? Str::random(48) : null]);

        return redirect()->route('tickets.show', $ticket)->with('success', $enabled
            ? 'Link publik aktif. Siapa pun yang punya link bisa melihat laporan ini.'
            : 'Link publik dimatikan.');
    }

    /**
     * The public report. No login, no account, read-only.
     */
    public function show(string $token)
    {
        $ticket = Ticket::where('share_token', $token)
            ->where('status', 'completed')
            ->firstOr(fn () => abort(404));

        return view('tickets.public', compact('ticket'));
    }
}
