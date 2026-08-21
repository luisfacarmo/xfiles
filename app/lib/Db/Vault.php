<?php

declare(strict_types=1);

namespace OCA\XFiles\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getPasswordHash()
 * @method void setPasswordHash(string $passwordHash)
 * @method string|null getRecoveryKeyHash()
 * @method void setRecoveryKeyHash(?string $recoveryKeyHash)
 * @method int getAutoLockSeconds()
 * @method void setAutoLockSeconds(int $autoLockSeconds)
 * @method int getMaxFileSizeMb()
 * @method void setMaxFileSizeMb(int $maxFileSizeMb)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 * @method string getUpdatedAt()
 * @method void setUpdatedAt(string $updatedAt)
 */
class Vault extends Entity {

    protected string $userId = '';
    protected string $passwordHash = '';
    protected ?string $recoveryKeyHash = null;
    protected int $autoLockSeconds = 300;
    protected int $maxFileSizeMb = 50;
    protected string $createdAt = '';
    protected string $updatedAt = '';

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('autoLockSeconds', 'integer');
        $this->addType('maxFileSizeMb', 'integer');
    }
}
