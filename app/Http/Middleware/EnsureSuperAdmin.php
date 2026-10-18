<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Lets only an active super admin through. Alias: "super". */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Admin || ! $user->isSuperAdmin() || ! $user->active) {
            return response()->json(['message' => 'Accès réservé.'], 403);
        }

        return $next($request);
    }
}