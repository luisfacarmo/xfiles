<?php

declare(strict_types=1);

namespace OCA\XFiles\Service;

use OCA\XFiles\Db\VaultImage;
use OCA\XFiles\Db\VaultImageMapper;
use OCA\XFiles\Db\VaultMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\IAppData;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

class ImageService {

    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/heic',
        'image/heif',
        'image/bmp',
        'image/tiff',
    ];

    private const THUMB_SIZE = 256;
    private const THUMB_QUALITY = 80;

    public function __construct(
        private IAppData $appData,
        private VaultImageMapper $imageMapper,
        private VaultMapper $vaultMapper,
        private ISecureRandom $secureRandom,
        private IRootFolder $rootFolder,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * List images for a user.
     *
     * @return array{images: array, total: int}
     */
    public function list(string $userId, int $limit = 100, int $offset = 0): array {
        $images = $this->imageMapper->findAllByUser($userId, $limit, $offset);
        $total = $this->imageMapper->countByUser($userId);

        return [
            'images' => array_map(fn(VaultImage $img) => $img->toApi(), $images),
            'total' => $total,
        ];
    }

    /**
     * Upload an image to the vault.
     *
     * @throws InvalidImageException
     * @throws VaultNotFoundException
     */
    public function upload(string $userId, string $tmpPath, string $originalName, string $mimeType): VaultImage {
        // Validate vault exists
        try {
            $vault = $this->vaultMapper->findByUserId($userId);
        } catch (DoesNotExistException) {
            throw new VaultNotFoundException('No vault found');
        }

        // Validate MIME type (from finfo, not user-supplied)
        $detectedMime = $this->detectMimeType($tmpPath);
        if (!in_array($detectedMime, self::ALLOWED_MIMES, true)) {
            throw new InvalidImageException("File type not allowed: {$detectedMime}");
        }

        // Validate file size
        $fileSize = filesize($tmpPath);
        $maxSizeBytes = $vault->getMaxFileSizeMb() * 1024 * 1024;
        if ($fileSize > $maxSizeBytes) {
            throw new InvalidImageException("File exceeds maximum size of {$vault->getMaxFileSizeMb()}MB");
        }

        // Generate unique storage name (alphanumeric only, no path separators)
        $extension = $this->getExtensionForMime($detectedMime);
        $storageName = 'img_' . $this->secureRandom->generate(24, ISecureRandom::CHAR_ALPHANUMERIC) . '.' . $extension;

        // Get image dimensions
        [$width, $height] = $this->getImageDimensions($tmpPath);

        // Compute checksum
        $checksum = hash_file('sha256', $tmpPath);

        // Store file in AppData
        $folder = $this->getUserFolder($userId);
        $file = $folder->newFile($storageName);
        $file->putContent(file_get_contents($tmpPath));

        // Generate thumbnail (background job in hybrid mode — for now inline)
        $hasThumbnail = $this->generateThumbnail($tmpPath, $userId, $storageName, $detectedMime);

        // Save metadata to DB
        $image = new VaultImage();
        $image->setVaultId($vault->getId());
        $image->setUserId($userId);
        $image->setOriginalName($this->sanitizeFilename($originalName));
        $image->setStorageName($storageName);
        $image->setMimeType($detectedMime);
        $image->setSize($fileSize);
        $image->setWidth($width);
        $image->setHeight($height);
        $image->setChecksum($checksum);
        $image->setHasThumbnail($hasThumbnail);
        $image->setCreatedAt(date('Y-m-d H:i:s'));

        $this->imageMapper->insert($image);

        $this->logger->debug('Image uploaded to vault', [
            'app' => 'xfiles',
            'user' => $userId,
            'name' => $originalName,
            'size' => $fileSize,
        ]);

        return $image;
    }

    /**
     * Import a file from user's Nextcloud filesystem into the vault.
     * Resolves by fileId (preferred) or path.
     *
     * Safe move pattern:
     * 1. Copy content to AppData (vault)
     * 2. Verify the copy is intact (checksum)
     * 3. Delete original permanently (bypass trashbin)
     *
     * The original is NEVER deleted before the vault copy is confirmed.
     *
     * @throws InvalidImageException
     * @throws VaultNotFoundException
     */
    public function importFromUserFiles(string $userId, ?int $fileId = null, ?string $path = null): VaultImage {
        $opId = 'XF-' . date('Ymd') . '-' . bin2hex(random_bytes(4));
        $startTime = hrtime(true);

        // Resolve vault (must exist, but does NOT need to be unlocked)
        try {
            $vault = $this->vaultMapper->findByUserId($userId);
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            $this->logger->warning('xfiles.classify.failure', [
                'app' => 'xfiles',
                'op' => $opId,
                'user' => $userId,
                'step' => 'resolve_vault',
                'error' => 'No vault exists for user',
            ]);
            throw new VaultNotFoundException('No vault found — set up X-Files first');
        }

        $this->logger->info('xfiles.classify.start', [
            'app' => 'xfiles',
            'op' => $opId,
            'user' => $userId,
            'fileId' => $fileId,
            'path' => $path,
            'vault_id' => $vault->getId(),
        ]);

        $userFolder = $this->rootFolder->getUserFolder($userId);
        $node = null;

        // Step: Resolve source file
        if ($fileId !== null) {
            $nodes = $userFolder->getById($fileId);
            if (empty($nodes)) {
                $this->logger->warning('xfiles.classify.failure', [
                    'app' => 'xfiles',
                    'op' => $opId,
                    'step' => 'resolve_source',
                    'error' => 'File not found by id',
                    'fileId' => $fileId,
                ]);
                throw new InvalidImageException('File not found (id: ' . $fileId . ')');
            }
            $node = $nodes[0];
        } elseif ($path !== null) {
            try {
                $node = $userFolder->get($path);
            } catch (NotFoundException) {
                $this->logger->warning('xfiles.classify.failure', [
                    'app' => 'xfiles',
                    'op' => $opId,
                    'step' => 'resolve_source',
                    'error' => 'File not found by path',
                    'path' => $path,
                ]);
                throw new InvalidImageException('File not found: ' . basename($path));
            }
        } else {
            throw new InvalidImageException('Either fileId or path is required');
        }

        if (!($node instanceof \OCP\Files\File)) {
            $this->logger->warning('xfiles.classify.failure', [
                'app' => 'xfiles',
                'op' => $opId,
                'step' => 'resolve_source',
                'error' => 'Not a file',
            ]);
            throw new InvalidImageException('Path is not a file');
        }

        $this->logger->debug('xfiles.classify.step', [
            'app' => 'xfiles',
            'op' => $opId,
            'step' => 'source_resolved',
            'name' => $node->getName(),
            'size' => $node->getSize(),
            'mime' => $node->getMimeType(),
        ]);

        // Step: Copy to temp + upload to vault
        $tmpFile = tempnam(sys_get_temp_dir(), 'xfiles_import_');
        try {
            $content = $node->getContent();
            file_put_contents($tmpFile, $content);

            $this->logger->debug('xfiles.classify.step', [
                'app' => 'xfiles',
                'op' => $opId,
                'step' => 'copy_to_vault',
            ]);

            $image = $this->upload(
                $userId,
                $tmpFile,
                $node->getName(),
                $node->getMimeType()
            );

            // Step: Verify integrity
            $this->logger->debug('xfiles.classify.step', [
                'app' => 'xfiles',
                'op' => $opId,
                'step' => 'integrity_check',
                'storage_name' => $image->getStorageName(),
            ]);

            $vaultFolder = $this->getUserFolder($userId);
            $vaultFile = $vaultFolder->getFile($image->getStorageName());
            $vaultChecksum = hash('sha256', $vaultFile->getContent());

            if ($vaultChecksum !== $image->getChecksum()) {
                $this->logger->error('xfiles.classify.failure', [
                    'app' => 'xfiles',
                    'op' => $opId,
                    'step' => 'integrity_check',
                    'error' => 'Checksum mismatch',
                    'expected' => $image->getChecksum(),
                    'got' => $vaultChecksum,
                ]);
                $this->delete($image->getId(), $userId);
                throw new InvalidImageException('Import verification failed — original file preserved');
            }

            // Step: Delete original permanently
            $nodePath = $node->getPath();
            $this->logger->debug('xfiles.classify.step', [
                'app' => 'xfiles',
                'op' => $opId,
                'step' => 'delete_source',
                'path' => $nodePath,
                'trashbin_disabled' => true,
            ]);

            \OCA\XFiles\Listener\BypassTrashbinListener::flagForPermanentDelete($nodePath);
            $sourceDeleted = true;
            try {
                $node->delete();
            } catch (\Throwable $e) {
                $sourceDeleted = false;
                \OCA\XFiles\Listener\BypassTrashbinListener::clearFlag($nodePath);
                $this->logger->warning('xfiles.classify.step', [
                    'app' => 'xfiles',
                    'op' => $opId,
                    'step' => 'delete_source_failed',
                    'path' => $nodePath,
                    'error' => $e->getMessage(),
                ]);
            }

            // Success
            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);
            $this->logger->info('xfiles.classify.success', [
                'app' => 'xfiles',
                'op' => $opId,
                'user' => $userId,
                'image_id' => $image->getId(),
                'original_name' => $node->getName(),
                'source_deleted' => $sourceDeleted,
                'trashbin_bypassed' => $sourceDeleted,
                'duration_ms' => $durationMs,
            ]);

            return $image;
        } catch (InvalidImageException $e) {
            // Re-throw after logging (already logged above for integrity)
            throw $e;
        } catch (\Throwable $e) {
            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);
            $this->logger->error('xfiles.classify.failure', [
                'app' => 'xfiles',
                'op' => $opId,
                'user' => $userId,
                'step' => 'unexpected',
                'error' => $e->getMessage(),
                'duration_ms' => $durationMs,
            ]);
            throw new InvalidImageException('Classification failed: ' . $e->getMessage());
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    /**
     * Get image file content.
     *
     * @throws VaultNotFoundException
     */
    public function getContent(int $imageId, string $userId): array {
        try {
            $image = $this->imageMapper->findById($imageId, $userId);
        } catch (DoesNotExistException) {
            throw new VaultNotFoundException('Image not found');
        }

        $folder = $this->getUserFolder($userId);
        try {
            $file = $folder->getFile($image->getStorageName());
        } catch (NotFoundException) {
            throw new VaultNotFoundException('Image file not found in storage');
        }

        return [
            'content' => $file->getContent(),
            'mime_type' => $image->getMimeType(),
            'original_name' => $image->getOriginalName(),
            'size' => $image->getSize(),
        ];
    }

    /**
     * Get thumbnail content.
     *
     * @throws VaultNotFoundException
     */
    public function getThumbnail(int $imageId, string $userId): array {
        try {
            $image = $this->imageMapper->findById($imageId, $userId);
        } catch (DoesNotExistException) {
            throw new VaultNotFoundException('Image not found');
        }

        if (!$image->getHasThumbnail()) {
            throw new VaultNotFoundException('Thumbnail not available');
        }

        $folder = $this->getUserFolder($userId);
        $thumbName = 'thumb_' . $image->getStorageName();

        try {
            $file = $folder->getFile($thumbName);
        } catch (NotFoundException) {
            throw new VaultNotFoundException('Thumbnail file not found');
        }

        return [
            'content' => $file->getContent(),
            'mime_type' => 'image/jpeg',
        ];
    }

    /**
     * Delete an image from the vault.
     *
     * @throws VaultNotFoundException
     */
    public function delete(int $imageId, string $userId): void {
        try {
            $image = $this->imageMapper->findById($imageId, $userId);
        } catch (DoesNotExistException) {
            throw new VaultNotFoundException('Image not found');
        }

        // Delete files from AppData
        $folder = $this->getUserFolder($userId);
        try {
            $folder->getFile($image->getStorageName())->delete();
        } catch (NotFoundException) {
            // File already gone — continue
        }

        // Delete thumbnail
        try {
            $folder->getFile('thumb_' . $image->getStorageName())->delete();
        } catch (NotFoundException) {
            // No thumbnail — fine
        }

        // Delete DB record
        $this->imageMapper->delete($image);

        $this->logger->debug('Image deleted from vault', [
            'app' => 'xfiles',
            'user' => $userId,
            'id' => $imageId,
        ]);
    }

    /**
     * Get or create user's AppData folder.
     */
    private function getUserFolder(string $userId): ISimpleFolder {
        try {
            return $this->appData->getFolder($userId);
        } catch (NotFoundException) {
            return $this->appData->newFolder($userId);
        }
    }

    /**
     * Detect real MIME type using finfo (not trusting client).
     */
    private function detectMimeType(string $path): string {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return $finfo->file($path) ?: 'application/octet-stream';
    }

    /**
     * Get file extension for MIME type.
     */
    private function getExtensionForMime(string $mime): string {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
            default => 'dat',
        };
    }

    /**
     * Get image dimensions.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function getImageDimensions(string $path): array {
        $info = @getimagesize($path);
        if ($info === false) {
            return [null, null];
        }
        return [$info[0], $info[1]];
    }

    /**
     * Generate a JPEG thumbnail and store in AppData.
     */
    private function generateThumbnail(string $sourcePath, string $userId, string $storageName, string $mimeType): bool {
        try {
            $source = $this->createImageResource($sourcePath, $mimeType);
            if ($source === null) {
                return false;
            }

            $origWidth = imagesx($source);
            $origHeight = imagesy($source);

            // Calculate crop for square thumbnail
            $cropSize = min($origWidth, $origHeight);
            $cropX = (int) (($origWidth - $cropSize) / 2);
            $cropY = (int) (($origHeight - $cropSize) / 2);

            // Create thumbnail
            $thumb = imagecreatetruecolor(self::THUMB_SIZE, self::THUMB_SIZE);
            imagecopyresampled(
                $thumb, $source,
                0, 0, $cropX, $cropY,
                self::THUMB_SIZE, self::THUMB_SIZE, $cropSize, $cropSize
            );

            // Save to temp file
            $tmpThumb = tempnam(sys_get_temp_dir(), 'xfiles_thumb_');
            imagejpeg($thumb, $tmpThumb, self::THUMB_QUALITY);

            imagedestroy($source);
            imagedestroy($thumb);

            // Store in AppData
            $folder = $this->getUserFolder($userId);
            $thumbFile = $folder->newFile('thumb_' . $storageName);
            $thumbFile->putContent(file_get_contents($tmpThumb));

            unlink($tmpThumb);

            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to generate thumbnail', [
                'app' => 'xfiles',
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Create GD image resource from file.
     */
    private function createImageResource(string $path, string $mimeType): ?\GdImage {
        return match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($path) ?: null,
            'image/png' => @imagecreatefrompng($path) ?: null,
            'image/gif' => @imagecreatefromgif($path) ?: null,
            'image/webp' => @imagecreatefromwebp($path) ?: null,
            'image/bmp' => @imagecreatefrombmp($path) ?: null,
            default => null,
        };
    }

    /**
     * Sanitize filename (remove path components, limit length).
     */
    private function sanitizeFilename(string $name): string {
        $name = basename($name);
        $name = preg_replace('/[^\w\s\-.]/', '_', $name) ?? $name;
        return mb_substr($name, 0, 200);
    }
}
