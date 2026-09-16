<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();

        $token = $plain ? ApiToken::findByPlainToken($plain) : null;

        if (! $token || ! $token->user) {
            return response()->json([
                'message' => 'Token tidak valid. Kirim header: Authorization: Bearer <token>.',
            ], 401);
        }

        // Cheap enough to write on every call, and makes unused tokens obvious.
        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        $request->setUserResolver(fn () => $token->user);

        return $next($request);
    }
}
