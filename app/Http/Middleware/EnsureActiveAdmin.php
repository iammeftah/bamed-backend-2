<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Refuses a valid token whose account was deactivated. Alias: "active". */
class EnsureActiveAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Admin || ! $user->active) {
            return response()->json(['message' => 'Compte désactivé.'], 403);
        }

        return $next($request);
    }
}