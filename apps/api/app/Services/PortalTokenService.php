<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\PortalAccessToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates and validates the bearer tokens used by the couples' private
 * portal links. Only a SHA-256 hash of the token is ever persisted; the
 * plaintext value is returned exactly once, at generation time, and can
 * never be recovered afterwards.
 */
class PortalTokenService
{
    /**
     * @return array{token: PortalAccessToken, plainText: string}
     */
    public function generate(Enrollment $enrollment, ?\DateTimeInterface $expiresAt = null): array
    {
        // 256 bits of entropy, base64url-encoded (URL-safe, no padding).
        $plainText = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $token = PortalAccessToken::create([
            'enrollment_id' => $enrollment->id,
            'token_hash' => $this->hash($plainText),
            'expires_at' => $expiresAt,
        ]);

        return ['token' => $token, 'plainText' => $plainText];
    }

    /**
     * Revoke every currently-valid token for the enrollment and issue a new
     * one, inside a single transaction.
     *
     * @return array{token: PortalAccessToken, plainText: string}
     */
    public function regenerate(Enrollment $enrollment, ?\DateTimeInterface $expiresAt = null): array
    {
        return DB::transaction(function () use ($enrollment, $expiresAt) {
            $this->revokeAll($enrollment);

            return $this->generate($enrollment, $expiresAt);
        });
    }

    public function revokeAll(Enrollment $enrollment): void
    {
        $enrollment->accessTokens()
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function findValidByPlainText(string $plainText): ?PortalAccessToken
    {
        $token = PortalAccessToken::where('token_hash', $this->hash($plainText))->first();

        if (! $token || ! $token->isValid()) {
            return null;
        }

        return $token;
    }

    protected function hash(string $plainText): string
    {
        return hash('sha256', $plainText);
    }
}
