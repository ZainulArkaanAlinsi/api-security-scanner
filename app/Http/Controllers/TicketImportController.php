<?php

namespace App\Http\Controllers;

use App\Jobs\ScanTicketJob;
use App\Services\OpenApiImporter;
use App\Services\ScanException;
use Illuminate\Http\Request;

class TicketImportController extends Controller
{
    public function create()
    {
        return view('tickets.import');
    }

    public function store(Request $request, OpenApiImporter $importer)
    {
        $data = $request->validate([
            'spec_url' => 'required|url:http,https|max:2048',
            'scan_now' => 'nullable|boolean',
        ], [
            'spec_url.url' => 'URL dokumen harus valid dan diawali http:// atau https://.',
        ]);

        try {
            $endpoints = $importer->import($data['spec_url']);
        } catch (ScanException $e) {
            return back()->withInput()->withErrors(['spec_url' => $e->getMessage()]);
        }

        $user = $request->user();
        $existing = $user->tickets()->pluck('api_url')->all();

        $created = 0;
        $skipped = 0;

        foreach ($endpoints as $endpoint) {
            if (in_array($endpoint['url'], $existing, true)) {
                $skipped++;

                continue;
            }

            $ticket = $user->tickets()->create([
                'title' => mb_substr($endpoint['title'], 0, 255),
                'api_url' => $endpoint['url'],
            ]);

            $existing[] = $endpoint['url'];
            $created++;

            if ($request->boolean('scan_now')) {
                $ticket->update(['status' => 'scanning']);
                ScanTicketJob::dispatch($ticket);
            }
        }

        $message = "{$created} endpoint diimpor.";

        if ($skipped > 0) {
            $message .= " {$skipped} dilewati karena sudah ada.";
        }

        if ($request->boolean('scan_now') && $created > 0) {
            $message .= ' Scan berjalan di latar belakang.';
        }

        return redirect()->route('tickets.index')->with('success', $message);
    }
}
