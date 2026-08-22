<?php

declare(strict_types=1);

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        // Image operations (regular routes for binary + JSON)
        ['name' => 'image#index', 'url' => '/api/v1/images', 'verb' => 'GET'],
        ['name' => 'image#upload', 'url' => '/api/v1/images/upload', 'verb' => 'POST'],
        ['name' => 'image#import', 'url' => '/api/v1/images/import', 'verb' => 'POST'],
        ['name' => 'image#show', 'url' => '/api/v1/images/{id}/download', 'verb' => 'GET'],
        ['name' => 'image#thumbnail', 'url' => '/api/v1/images/{id}/thumb', 'verb' => 'GET'],
        ['name' => 'image#destroy', 'url' => '/api/v1/images/{id}', 'verb' => 'DELETE'],
    ],
    'ocs' => [
        // Vault lifecycle (OCS for structured responses)
        ['name' => 'vault#status', 'url' => '/api/v1/vault/status', 'verb' => 'GET'],
        ['name' => 'vault#setup', 'url' => '/api/v1/vault/setup', 'verb' => 'POST'],
        ['name' => 'vault#unlock', 'url' => '/api/v1/vault/unlock', 'verb' => 'POST'],
        ['name' => 'vault#lock', 'url' => '/api/v1/vault/lock', 'verb' => 'POST'],
        ['name' => 'vault#recover', 'url' => '/api/v1/vault/recover', 'verb' => 'POST'],
        ['name' => 'vault#change_password', 'url' => '/api/v1/vault/password', 'verb' => 'POST'],
        ['name' => 'vault#update_settings', 'url' => '/api/v1/vault/settings', 'verb' => 'POST'],
    ],
];
