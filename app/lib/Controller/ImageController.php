<?php

declare(strict_types=1);

namespace OCA\XFiles\Controller;

use OCA\XFiles\AppInfo\Application;
use OCA\XFiles\Service\ImageService;
use OCA\XFiles\Service\InvalidImageException;
use OCA\XFiles\Service\VaultNotFoundException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class ImageController extends Controller {

    public function __construct(
        IRequest $request,
        private ImageService $imageService,
        private IUserSession $userSession,
        private LoggerInterface $logger,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * List vault images (paginated).
     */
    #[NoAdminRequired]
    public function index(int $limit = 100, int $offset = 0): JSONResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        $result = $this->imageService->list($userId, min($limit, 500), max($offset, 0));
        return new JSONResponse($result);
    }

    /**
     * Upload an image to the vault.
     */
    #[NoAdminRequired]
    public function upload(): JSONResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        $file = $this->request->getUploadedFile('file');
        if ($file === null || $file['error'] !== UPLOAD_ERR_OK) {
            $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            return new JSONResponse(
                ['error' => $this->getUploadError($errorCode), 'code' => 'UPLOAD_FAILED'],
                Http::STATUS_BAD_REQUEST
            );
        }

        try {
            $image = $this->imageService->upload(
                $userId,
                $file['tmp_name'],
                $file['name'],
                $file['type']
            );
            return new JSONResponse([
                'success' => true,
                'image' => $image->toApi(),
            ]);
        } catch (InvalidImageException $e) {
            return new JSONResponse(
                ['error' => $e->getMessage(), 'code' => 'INVALID_IMAGE'],
                Http::STATUS_BAD_REQUEST
            );
        } catch (VaultNotFoundException $e) {
            return new JSONResponse(
                ['error' => 'Vault not found', 'code' => 'VAULT_NOT_FOUND'],
                Http::STATUS_NOT_FOUND
            );
        }
    }

    /**
     * Download full image.
     */
    #[NoAdminRequired]
    public function show(int $id): DataDownloadResponse|JSONResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $data = $this->imageService->getContent($id, $userId);
            $response = new DataDownloadResponse(
                $data['content'],
                $data['original_name'],
                $data['mime_type']
            );
            $response->addHeader('Content-Disposition', 'inline; filename="' . $data['original_name'] . '"');
            return $response;
        } catch (VaultNotFoundException $e) {
            return new JSONResponse(
                ['error' => 'Image not found', 'code' => 'NOT_FOUND'],
                Http::STATUS_NOT_FOUND
            );
        }
    }

    /**
     * Get image thumbnail.
     */
    #[NoAdminRequired]
    public function thumbnail(int $id): DataDownloadResponse|JSONResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $data = $this->imageService->getThumbnail($id, $userId);
            $response = new DataDownloadResponse(
                $data['content'],
                "thumb_{$id}.jpg",
                $data['mime_type']
            );
            $response->addHeader('Content-Disposition', 'inline');
            $response->addHeader('Cache-Control', 'private, max-age=3600');
            return $response;
        } catch (VaultNotFoundException $e) {
            return new JSONResponse(
                ['error' => 'Thumbnail not found', 'code' => 'NOT_FOUND'],
                Http::STATUS_NOT_FOUND
            );
        }
    }

    /**
     * Delete an image from the vault.
     */
    #[NoAdminRequired]
    public function destroy(int $id): JSONResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $this->imageService->delete($id, $userId);
            return new JSONResponse(['success' => true]);
        } catch (VaultNotFoundException $e) {
            return new JSONResponse(
                ['error' => 'Image not found', 'code' => 'NOT_FOUND'],
                Http::STATUS_NOT_FOUND
            );
        }
    }

    private function getUserId(): ?string {
        $user = $this->userSession->getUser();
        return $user?->getUID();
    }

    private function getUploadError(int $code): string {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is too large',
            UPLOAD_ERR_PARTIAL => 'Upload was interrupted',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
            default => 'Upload failed',
        };
    }
}
