<?php

declare(strict_types=1);

namespace OCA\XFiles\Settings;

use OCA\XFiles\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings {

    public function __construct(
        private IConfig $config,
        private IDBConnection $db,
    ) {
    }

    public function getForm(): TemplateResponse {
        $appId = Application::APP_ID;
        $params = [
            'enabled_groups' => $this->config->getAppValue($appId, 'enabled_groups', ''),
            'max_file_size_mb' => (int) $this->config->getAppValue($appId, 'max_file_size_mb', '50'),
            'default_auto_lock_seconds' => (int) $this->config->getAppValue($appId, 'default_auto_lock_seconds', '300'),
            'allow_nc_password_recovery' => $this->config->getAppValue($appId, 'allow_nc_password_recovery', 'true'),
            'stats' => $this->getStats(),
        ];

        return new TemplateResponse(Application::APP_ID, 'settings/admin', $params, '');
    }

    public function getSection(): string {
        return 'xfiles';
    }

    public function getPriority(): int {
        return 50;
    }

    private function getStats(): array {
        $qb = $this->db->getQueryBuilder();

        // Count vaults
        $qb->select($qb->createFunction('COUNT(*)'))
            ->from('xfiles_vaults');
        $result = $qb->executeQuery();
        $vaultCount = (int) $result->fetchOne();
        $result->closeCursor();

        // Count images
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->createFunction('COUNT(*)'))
            ->from('xfiles_images');
        $result = $qb->executeQuery();
        $imageCount = (int) $result->fetchOne();
        $result->closeCursor();

        // Total size
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->createFunction('COALESCE(SUM(size), 0)'))
            ->from('xfiles_images');
        $result = $qb->executeQuery();
        $totalSize = (int) $result->fetchOne();
        $result->closeCursor();

        return [
            'vault_count' => $vaultCount,
            'image_count' => $imageCount,
            'total_size' => $totalSize,
        ];
    }
}
