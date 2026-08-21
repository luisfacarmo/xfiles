<?php

declare(strict_types=1);

namespace OCA\XFiles\Middleware;

use OCA\XFiles\Controller\ImageController;
use OCA\XFiles\Service\SessionService;
use OCA\XFiles\Service\VaultService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Middleware;
use OCP\IUserSession;

/**
 * Middleware that blocks access to image endpoints when the vault is locked.
 *
 * Only applies to ImageController (future). VaultController and PageController
 * are exempt — they need to work regardless of vault state.
 */
class VaultSessionMiddleware extends Middleware {

    public function __construct(
        private SessionService $sessionService,
        private IUserSession $userSession,
        private VaultService $vaultService,
    ) {
    }

    public function beforeController($controller, $methodName): void {
        // Only gate ImageController (added in Fase 3)
        if (!($controller instanceof ImageController)) {
            return;
        }

        $user = $this->userSession->getUser();
        if ($user === null) {
            throw new VaultLockedException('Not authenticated');
        }

        $userId = $user->getUID();
        $status = $this->vaultService->getStatus($userId);

        if ($status['status'] !== 'unlocked') {
            throw new VaultLockedException('Vault is locked');
        }

        // Refresh the session timer on successful access
        $this->sessionService->touch();
    }

    public function afterException($controller, $methodName, \Exception $exception): JSONResponse {
        if ($exception instanceof VaultLockedException) {
            return new JSONResponse(
                ['error' => 'Vault is locked', 'code' => 'VAULT_LOCKED'],
                Http::STATUS_FORBIDDEN
            );
        }

        throw $exception;
    }
}
