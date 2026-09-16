<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validateWithBag('token', [
            'name' => 'required|string|max:60',
        ]);

        ['token' => $plain] = ApiToken::issue($request->user(), $data['name']);

        // Shown once: only the hash is stored.
        return back()->with('new_api_token', $plain);
    }

    public function destroy(Request $request, ApiToken $token)
    {
        abort_unless($token->user_id === $request->user()->id, 404);

        $token->delete();

        return back()->with('success', 'Token dihapus. Pipeline yang memakainya akan langsung ditolak.');
    }
}
