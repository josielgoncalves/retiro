<?php

namespace App\Http\Middleware;

use App\Services\PortalTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the {token} route parameter against portal_access_tokens and
 * binds the matching enrollment onto the request. Invalid, expired and
 * revoked tokens all get the same generic response so a caller can never
 * tell those three cases apart.
 */
class AuthenticatePortalToken
{
    public function __construct(protected PortalTokenService $tokenService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $plainText = (string) $request->route('token');

        $token = $this->tokenService->findValidByPlainText($plainText);

        if (! $token) {
            abort(404, 'Link inválido ou expirado.');
        }

        $token->forceFill(['last_access_at' => now()])->save();

        $request->attributes->set('portalEnrollment', $token->enrollment);

        return $next($request);
    }
}
