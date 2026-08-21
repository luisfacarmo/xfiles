<?php

declare(strict_types=1);

namespace OCA\XFiles\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Vault>
 */
class VaultMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'xfiles_vaults', Vault::class);
    }

    /**
     * @throws DoesNotExistException
     */
    public function findByUserId(string $userId): Vault {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        return $this->findEntity($qb);
    }

    public function existsForUser(string $userId): bool {
        try {
            $this->findByUserId($userId);
            return true;
        } catch (DoesNotExistException) {
            return false;
        }
    }
}
