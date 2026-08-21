<?php

declare(strict_types=1);

namespace OCA\XFiles\Service;

use OCA\XFiles\Db\Vault;
use OCA\XFiles\Db\VaultMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class VaultService {

    public function __construct(
        private VaultMapper $vaultMapper,
        private PasswordService $passwordService,
        private SessionService $sessionService,
    ) {
    }

    /**
     * Get vault status for a user.
     *
     * @return array{status: string, auto_lock_seconds?: int, remaining_seconds?: int}
     */
    public function getStatus(string $userId): array {
        try {
            $vault = $this->vaultMapper->findByUserId($userId);
        } catch (DoesNotExistException) {
            return ['status' => 'not_setup'];
        }

        $autoLock = $vault->getAutoLockSeconds();

        if ($this->sessionService->isUnlocked($userId, $autoLock)) {
            return [
                'status' => 'unlocked',
                'auto_lock_seconds' => $autoLock,
                'remaining_seconds' => $this->sessionService->getRemainingSeconds($autoLock),
            ];
        }

        return [
            'status' => 'locked',
            'auto_lock_seconds' => $autoLock,
        ];
    }

    /**
     * Create a new vault for a user.
     *
     * @return array{recovery_key: string}
     * @throws VaultAlreadyExistsException
     * @throws InvalidPasswordException
     */
    public function setup(string $userId, string $password): array {
        if ($this->vaultMapper->existsForUser($userId)) {
            throw new VaultAlreadyExistsException('Vault already exists for this user');
        }

        if (!$this->passwordService->validate($password)) {
            throw new InvalidPasswordException('Password must be at least 4 characters');
        }

        $recoveryKey = $this->passwordService->generateRecoveryKey();
        $now = date('Y-m-d H:i:s');

        $vault = new Vault();
        $vault->setUserId($userId);
        $vault->setPasswordHash($this->passwordService->hash($password));
        $vault->setRecoveryKeyHash($recoveryKey['hash']);
        $vault->setAutoLockSeconds(300);
        $vault->setMaxFileSizeMb(50);
        $vault->setCreatedAt($now);
        $vault->setUpdatedAt($now);

        $this->vaultMapper->insert($vault);

        // Auto-unlock after setup
        $this->sessionService->unlock($userId);

        return ['recovery_key' => $recoveryKey['plain']];
    }

    /**
     * Unlock the vault with a password.
     *
     * @throws VaultNotFoundException
     * @throws InvalidPasswordException
     */
    public function unlock(string $userId, string $password): void {
        $vault = $this->getVaultOrFail($userId);

        if (!$this->passwordService->verify($password, $vault->getPasswordHash())) {
            throw new InvalidPasswordException('Invalid vault password');
        }

        // Rehash if algorithm was upgraded
        if ($this->passwordService->needsRehash($vault->getPasswordHash())) {
            $vault->setPasswordHash($this->passwordService->hash($password));
            $vault->setUpdatedAt(date('Y-m-d H:i:s'));
            $this->vaultMapper->update($vault);
        }

        $this->sessionService->unlock($userId);
    }

    /**
     * Lock the vault.
     */
    public function lock(string $userId): void {
        $this->sessionService->lock();
    }

    /**
     * Change the vault password.
     *
     * @throws VaultNotFoundException
     * @throws InvalidPasswordException
     */
    public function changePassword(string $userId, string $currentPassword, string $newPassword): void {
        $vault = $this->getVaultOrFail($userId);

        if (!$this->passwordService->verify($currentPassword, $vault->getPasswordHash())) {
            throw new InvalidPasswordException('Current password is incorrect');
        }

        if (!$this->passwordService->validate($newPassword)) {
            throw new InvalidPasswordException('New password must be at least 4 characters');
        }

        $vault->setPasswordHash($this->passwordService->hash($newPassword));
        $vault->setUpdatedAt(date('Y-m-d H:i:s'));
        $this->vaultMapper->update($vault);

        // Re-lock after password change (force re-authentication)
        $this->sessionService->lock();
    }

    /**
     * Reset vault password using recovery key.
     *
     * @throws VaultNotFoundException
     * @throws InvalidPasswordException
     */
    public function resetWithRecoveryKey(string $userId, string $recoveryKey, string $newPassword): void {
        $vault = $this->getVaultOrFail($userId);

        $recoveryHash = $vault->getRecoveryKeyHash();
        if ($recoveryHash === null || !$this->passwordService->verify($recoveryKey, $recoveryHash)) {
            throw new InvalidPasswordException('Invalid recovery key');
        }

        if (!$this->passwordService->validate($newPassword)) {
            throw new InvalidPasswordException('New password must be at least 4 characters');
        }

        $vault->setPasswordHash($this->passwordService->hash($newPassword));
        $vault->setUpdatedAt(date('Y-m-d H:i:s'));
        $this->vaultMapper->update($vault);

        $this->sessionService->lock();
    }

    /**
     * Reset vault password using Nextcloud account password.
     * This requires the caller to have already verified the NC password.
     *
     * @throws VaultNotFoundException
     * @throws InvalidPasswordException
     */
    public function resetWithAccountPassword(string $userId, string $newPassword): void {
        $vault = $this->getVaultOrFail($userId);

        if (!$this->passwordService->validate($newPassword)) {
            throw new InvalidPasswordException('New password must be at least 4 characters');
        }

        $vault->setPasswordHash($this->passwordService->hash($newPassword));
        $vault->setUpdatedAt(date('Y-m-d H:i:s'));
        $this->vaultMapper->update($vault);

        $this->sessionService->lock();
    }

    /**
     * Get the vault entity or throw.
     *
     * @throws VaultNotFoundException
     */
    private function getVaultOrFail(string $userId): Vault {
        try {
            return $this->vaultMapper->findByUserId($userId);
        } catch (DoesNotExistException) {
            throw new VaultNotFoundException('No vault found for this user');
        }
    }
}
