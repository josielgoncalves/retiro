<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Services\AuditLogger;
use App\Services\PortalTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalAccessController extends Controller
{
    public function __construct(
        protected PortalTokenService $tokenService,
        protected AuditLogger $auditLogger,
    ) {}

    public function show(Enrollment $enrollment): JsonResponse
    {
        $this->authorize('manageAccessLink', $enrollment);

        $current = $enrollment->accessTokens()->whereNull('revoked_at')->latest()->first();

        return response()->json([
            'data' => [
                'has_active_token' => (bool) $current,
                'expires_at' => $current?->expires_at,
                'last_access_at' => $current?->last_access_at,
                'created_at' => $current?->created_at,
            ],
        ]);
    }

    public function regenerate(Request $request, Enrollment $enrollment): JsonResponse
    {
        $this->authorize('manageAccessLink', $enrollment);

        ['token' => $token, 'plainText' => $plainText] = $this->tokenService->regenerate($enrollment);

        $this->auditLogger->log('portal_token.regenerated', $token, null, ['enrollment_id' => $enrollment->id], $request->user());

        return response()->json([
            'data' => [
                'token' => $plainText,
                'portal_path' => "/c/{$plainText}",
                'expires_at' => $token->expires_at,
            ],
        ]);
    }

    public function revoke(Request $request, Enrollment $enrollment): JsonResponse
    {
        $this->authorize('manageAccessLink', $enrollment);

        $this->tokenService->revokeAll($enrollment);

        $this->auditLogger->log('portal_token.revoked', $enrollment, null, null, $request->user());

        return response()->json(['message' => 'Link privado revogado com sucesso.']);
    }
}
