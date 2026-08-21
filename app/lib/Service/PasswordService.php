<?php

declare(strict_types=1);

namespace OCA\XFiles\Service;

use OCP\Security\ISecureRandom;

class PasswordService {

    public function __construct(
        private ISecureRandom $secureRandom,
    ) {
    }

    /**
     * Hash a vault password using Argon2id.
     */
    public function hash(string $password): string {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    /**
     * Verify a password against a stored hash.
     * Timing-safe by design (password_verify).
     */
    public function verify(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    /**
     * Check if a password hash needs rehashing (algorithm upgrade).
     */
    public function needsRehash(string $hash): bool {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID);
    }

    /**
     * Generate a recovery key (24 chars, grouped for readability).
     * Format: XFLS-XXXX-XXXX-XXXX-XXXX-XXXX
     *
     * @return array{plain: string, hash: string}
     */
    public function generateRecoveryKey(): array {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no I/O/0/1 (ambiguous)
        $segments = [];
        for ($i = 0; $i < 5; $i++) {
            $segments[] = $this->secureRandom->generate(4, $chars);
        }

        $plain = 'XFLS-' . implode('-', $segments);
        $hash = $this->hash($plain);

        return [
            'plain' => $plain,
            'hash' => $hash,
        ];
    }

    /**
     * Validate password meets minimum requirements.
     */
    public function validate(string $password): bool {
        return strlen($password) >= 4;
    }
}
