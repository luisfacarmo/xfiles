<?php

declare(strict_types=1);

namespace OCA\XFiles\Service;

use OCP\ISession;

class SessionService {

    private const KEY_UNLOCKED = 'xfiles_vault_unlocked';
    private const KEY_UNLOCK_TIME = 'xfiles_vault_unlock_time';
    private const KEY_USER_ID = 'xfiles_vault_user_id';

    public function __construct(
        private ISession $session,
    ) {
    }

    /**
     * Mark the vault as unlocked for the current session.
     */
    public function unlock(string $userId): void {
        $this->session->set(self::KEY_UNLOCKED, true);
        $this->session->set(self::KEY_UNLOCK_TIME, time());
        $this->session->set(self::KEY_USER_ID, $userId);
    }

    /**
     * Lock the vault in the current session.
     */
    public function lock(): void {
        $this->session->remove(self::KEY_UNLOCKED);
        $this->session->remove(self::KEY_UNLOCK_TIME);
        $this->session->remove(self::KEY_USER_ID);
    }

    /**
     * Check if the vault is currently unlocked and not expired.
     */
    public function isUnlocked(string $userId, int $autoLockSeconds): bool {
        if (!$this->session->get(self::KEY_UNLOCKED)) {
            return false;
        }

        // Verify the session belongs to this user
        $sessionUserId = $this->session->get(self::KEY_USER_ID);
        if ($sessionUserId !== $userId) {
            return false;
        }

        // Check timeout (0 = never auto-lock)
        if ($autoLockSeconds > 0) {
            $unlockTime = (int) $this->session->get(self::KEY_UNLOCK_TIME);
            if ((time() - $unlockTime) > $autoLockSeconds) {
                $this->lock();
                return false;
            }
        }

        return true;
    }

    /**
     * Refresh the unlock timestamp (extends the session on activity).
     */
    public function touch(): void {
        if ($this->session->get(self::KEY_UNLOCKED)) {
            $this->session->set(self::KEY_UNLOCK_TIME, time());
        }
    }

    /**
     * Get seconds remaining before auto-lock (0 = expired or locked).
     */
    public function getRemainingSeconds(int $autoLockSeconds): int {
        if (!$this->session->get(self::KEY_UNLOCKED) || $autoLockSeconds <= 0) {
            return 0;
        }

        $unlockTime = (int) $this->session->get(self::KEY_UNLOCK_TIME);
        $elapsed = time() - $unlockTime;
        $remaining = $autoLockSeconds - $elapsed;

        return max(0, $remaining);
    }
}
