<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The couple's portal link is a bearer credential embedded in the URL.
 * These headers keep it out of caches, analytics referrers and search
 * engines.
 */
class SetPrivatePortalHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Referrer-Policy', 'no-referrer');
        // Symfony's ResponseHeaderBag merges Cache-Control directives rather
        // than replacing them outright, so clear it first to get exactly
        // "no-store" instead of e.g. "no-store, private".
        $response->headers->remove('Cache-Control');
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
