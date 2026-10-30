<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deactivating a user must take effect immediately, not just block their
 * next login — this re-checks `active` on every authenticated request so an
 * already-open session is cut off right away.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->active) {
            abort(403, 'Esta conta está desativada.');
        }

        return $next($request);
    }
}
