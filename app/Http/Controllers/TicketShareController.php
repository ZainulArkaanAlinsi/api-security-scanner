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
     * A shields-style badge for the shared report, so a project can show its
     * own API security grade in its README.
     */
    public function badge(string $token)
    {
        $ticket = Ticket::where('share_token', $token)
            ->where('status', 'completed')
            ->firstOr(fn () => abort(404));

        $grade = $ticket->grade ?? '?';
        $score = $ticket->score ?? 0;

        $colour = match (true) {
            $score >= 90 => '#16a34a',
            $score >= 80 => '#65a30d',
            $score >= 70 => '#ca8a04',
            $score >= 60 => '#ea580c',
            default => '#dc2626',
        };

        $label = 'API security';
        $value = "{$grade} · {$score}/100";

        $labelWidth = 20 + (int) round(strlen($label) * 6.2);
        $valueWidth = 20 + (int) round(mb_strlen($value) * 6.6);
        $total = $labelWidth + $valueWidth;
        $labelCentre = (int) round($labelWidth / 2);
        $valueCentre = (int) round($labelWidth + $valueWidth / 2);

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$total}" height="20" role="img" aria-label="{$label}: {$value}">
    <title>{$label}: {$value}</title>
    <linearGradient id="s" x2="0" y2="100%">
        <stop offset="0" stop-color="#fff" stop-opacity=".7"/>
        <stop offset=".1" stop-color="#aaa" stop-opacity=".1"/>
        <stop offset=".9" stop-color="#000" stop-opacity=".3"/>
        <stop offset="1" stop-color="#000" stop-opacity=".5"/>
    </linearGradient>
    <clipPath id="r"><rect width="{$total}" height="20" rx="3"/></clipPath>
    <g clip-path="url(#r)">
        <rect width="{$labelWidth}" height="20" fill="#24292f"/>
        <rect x="{$labelWidth}" width="{$valueWidth}" height="20" fill="{$colour}"/>
        <rect width="{$total}" height="20" fill="url(#s)"/>
    </g>
    <g fill="#fff" text-anchor="middle" font-family="Verdana,Geneva,DejaVu Sans,sans-serif" font-size="11">
        <text x="{$labelCentre}" y="14">{$label}</text>
        <text x="{$valueCentre}" y="14">{$value}</text>
    </g>
</svg>
SVG;

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            // Short cache so a re-scan shows up quickly, but repeated README
            // views do not hit the database every time.
            'Cache-Control' => 'public, max-age=300',
        ]);
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
