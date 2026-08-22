<?php

declare(strict_types=1);

namespace OCA\XFiles\Controller;

use OCA\XFiles\AppInfo\Application;
use OCA\XFiles\Service\InvalidPasswordException;
use OCA\XFiles\Service\VaultAlreadyExistsException;
use OCA\XFiles\Service\VaultNotFoundException;
use OCA\XFiles\Service\VaultService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class VaultController extends OCSController {

    public function __construct(
        IRequest $request,
        private VaultService $vaultService,
        private IUserSession $userSession,
        private LoggerInterface $logger,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Get vault status (not_setup / locked / unlocked).
     */
    #[NoAdminRequired]
    public function status(): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        $status = $this->vaultService->getStatus($userId);
        return new DataResponse($status);
    }

    /**
     * Create a new vault with a password.
     */
    #[NoAdminRequired]
    #[UserRateLimit(limit: 3, period: 300)]
    public function setup(string $password): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $result = $this->vaultService->setup($userId, $password);
            $this->logger->info('Vault created for user', ['app' => Application::APP_ID]);
            return new DataResponse([
                'success' => true,
                'recovery_key' => $result['recovery_key'],
            ]);
        } catch (VaultAlreadyExistsException $e) {
            return new DataResponse(
                ['error' => $e->getMessage(), 'code' => 'VAULT_EXISTS'],
                Http::STATUS_CONFLICT
            );
        } catch (InvalidPasswordException $e) {
            return new DataResponse(
                ['error' => $e->getMessage(), 'code' => 'INVALID_PASSWORD'],
                Http::STATUS_BAD_REQUEST
            );
        }
    }

    /**
     * Unlock the vault with a password.
     */
    #[NoAdminRequired]
    #[BruteForceProtection(action: 'xfiles_unlock')]
    #[UserRateLimit(limit: 5, period: 300)]
    public function unlock(string $password): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $this->vaultService->unlock($userId, $password);
            return new DataResponse(['success' => true]);
        } catch (VaultNotFoundException $e) {
            $response = new DataResponse(
                ['error' => 'Vault not found', 'code' => 'VAULT_NOT_FOUND'],
                Http::STATUS_NOT_FOUND
            );
            $response->throttle();
            return $response;
        } catch (InvalidPasswordException $e) {
            $response = new DataResponse(
                ['error' => 'Invalid password', 'code' => 'INVALID_PASSWORD'],
                Http::STATUS_FORBIDDEN
            );
            $response->throttle();
            return $response;
        }
    }

    /**
     * Lock the vault.
     */
    #[NoAdminRequired]
    public function lock(): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        $this->vaultService->lock($userId);
        return new DataResponse(['success' => true]);
    }

    /**
     * Recover vault access using recovery key.
     */
    #[NoAdminRequired]
    #[BruteForceProtection(action: 'xfiles_recover')]
    #[UserRateLimit(limit: 3, period: 300)]
    public function recover(string $recovery_key, string $new_password): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $this->vaultService->resetWithRecoveryKey($userId, $recovery_key, $new_password);
            $this->logger->info('Vault password reset via recovery key', ['app' => Application::APP_ID]);
            return new DataResponse(['success' => true]);
        } catch (VaultNotFoundException $e) {
            $response = new DataResponse(
                ['error' => 'Vault not found', 'code' => 'VAULT_NOT_FOUND'],
                Http::STATUS_NOT_FOUND
            );
            $response->throttle();
            return $response;
        } catch (InvalidPasswordException $e) {
            $response = new DataResponse(
                ['error' => $e->getMessage(), 'code' => 'INVALID_RECOVERY_KEY'],
                Http::STATUS_FORBIDDEN
            );
            $response->throttle();
            return $response;
        }
    }

    /**
     * Change vault password.
     */
    #[NoAdminRequired]
    #[BruteForceProtection(action: 'xfiles_change_password')]
    #[UserRateLimit(limit: 3, period: 300)]
    public function changePassword(string $current_password, string $new_password): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $this->vaultService->changePassword($userId, $current_password, $new_password);
            return new DataResponse(['success' => true]);
        } catch (VaultNotFoundException $e) {
            return new DataResponse(
                ['error' => 'Vault not found', 'code' => 'VAULT_NOT_FOUND'],
                Http::STATUS_NOT_FOUND
            );
        } catch (InvalidPasswordException $e) {
            $response = new DataResponse(
                ['error' => $e->getMessage(), 'code' => 'INVALID_PASSWORD'],
                Http::STATUS_FORBIDDEN
            );
            $response->throttle();
            return $response;
        }
    }

    /**
     * Update vault settings (auto_lock_seconds, max_file_size_mb).
     */
    #[NoAdminRequired]
    public function updateSettings(int $auto_lock_seconds, int $max_file_size_mb): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $this->vaultService->updateSettings($userId, $auto_lock_seconds, $max_file_size_mb);
            return new DataResponse(['success' => true]);
        } catch (VaultNotFoundException $e) {
            return new DataResponse(
                ['error' => 'Vault not found', 'code' => 'VAULT_NOT_FOUND'],
                Http::STATUS_NOT_FOUND
            );
        }
    }

    private function getUserId(): ?string {
        $user = $this->userSession->getUser();
        return $user?->getUID();
    }
}
