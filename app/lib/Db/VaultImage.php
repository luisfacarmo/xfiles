<?php

declare(strict_types=1);

namespace OCA\XFiles\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getVaultId()
 * @method void setVaultId(int $vaultId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getOriginalName()
 * @method void setOriginalName(string $originalName)
 * @method string getStorageName()
 * @method void setStorageName(string $storageName)
 * @method string getMimeType()
 * @method void setMimeType(string $mimeType)
 * @method int getSize()
 * @method void setSize(int $size)
 * @method int|null getWidth()
 * @method void setWidth(?int $width)
 * @method int|null getHeight()
 * @method void setHeight(?int $height)
 * @method string getChecksum()
 * @method void setChecksum(string $checksum)
 * @method bool getHasThumbnail()
 * @method void setHasThumbnail(bool $hasThumbnail)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 */
class VaultImage extends Entity {

    protected int $vaultId = 0;
    protected string $userId = '';
    protected string $originalName = '';
    protected string $storageName = '';
    protected string $mimeType = '';
    protected int $size = 0;
    protected ?int $width = null;
    protected ?int $height = null;
    protected string $checksum = '';
    protected bool $hasThumbnail = false;
    protected string $createdAt = '';

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('vaultId', 'integer');
        $this->addType('size', 'integer');
        $this->addType('width', 'integer');
        $this->addType('height', 'integer');
        $this->addType('hasThumbnail', 'boolean');
    }

    /**
     * Return public-safe representation (no storage path exposed).
     */
    public function toApi(): array {
        return [
            'id' => $this->getId(),
            'original_name' => $this->getOriginalName(),
            'mime_type' => $this->getMimeType(),
            'size' => $this->getSize(),
            'width' => $this->getWidth(),
            'height' => $this->getHeight(),
            'has_thumbnail' => $this->getHasThumbnail(),
            'created_at' => $this->getCreatedAt(),
        ];
    }
}
