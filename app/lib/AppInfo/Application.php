<?php

declare(strict_types=1);

namespace OCA\XFiles\AppInfo;

use OCA\XFiles\Middleware\VaultSessionMiddleware;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {

    public const APP_ID = 'xfiles';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerMiddleware(VaultSessionMiddleware::class);
    }

    public function boot(IBootContext $context): void {
        // Boot-time logic (after all apps registered)
    }
}
