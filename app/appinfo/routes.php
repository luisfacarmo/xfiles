<?php

declare(strict_types=1);

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
    ],
    'ocs' => [
        // Vault lifecycle
        ['name' => 'vault#status', 'url' => '/api/v1/vault/status', 'verb' => 'GET'],
        ['name' => 'vault#setup', 'url' => '/api/v1/vault/setup', 'verb' => 'POST'],
        ['name' => 'vault#unlock', 'url' => '/api/v1/vault/unlock', 'verb' => 'POST'],
        ['name' => 'vault#lock', 'url' => '/api/v1/vault/lock', 'verb' => 'POST'],
    ],
];
