<?php

declare(strict_types=1);

namespace OCA\XFiles\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version001000Date20260821 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // Vaults table
        if (!$schema->hasTable('xfiles_vaults')) {
            $table = $schema->createTable('xfiles_vaults');

            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('user_id', Types::STRING, [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('password_hash', Types::STRING, [
                'notnull' => true,
                'length' => 255,
            ]);
            $table->addColumn('recovery_key_hash', Types::STRING, [
                'notnull' => false,
                'length' => 255,
            ]);
            $table->addColumn('auto_lock_seconds', Types::INTEGER, [
                'notnull' => true,
                'default' => 300,
                'unsigned' => true,
            ]);
            $table->addColumn('max_file_size_mb', Types::INTEGER, [
                'notnull' => true,
                'default' => 50,
                'unsigned' => true,
            ]);
            $table->addColumn('created_at', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->addColumn('updated_at', Types::DATETIME, [
                'notnull' => true,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['user_id'], 'xfiles_vault_user_idx');
        }

        // Images table
        if (!$schema->hasTable('xfiles_images')) {
            $table = $schema->createTable('xfiles_images');

            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('vault_id', Types::BIGINT, [
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('user_id', Types::STRING, [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('original_name', Types::STRING, [
                'notnull' => true,
                'length' => 255,
            ]);
            $table->addColumn('storage_name', Types::STRING, [
                'notnull' => true,
                'length' => 128,
            ]);
            $table->addColumn('mime_type', Types::STRING, [
                'notnull' => true,
                'length' => 128,
            ]);
            $table->addColumn('size', Types::BIGINT, [
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('width', Types::INTEGER, [
                'notnull' => false,
                'unsigned' => true,
            ]);
            $table->addColumn('height', Types::INTEGER, [
                'notnull' => false,
                'unsigned' => true,
            ]);
            $table->addColumn('checksum', Types::STRING, [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('has_thumbnail', Types::BOOLEAN, [
                'notnull' => true,
                'default' => false,
            ]);
            $table->addColumn('created_at', Types::DATETIME, [
                'notnull' => true,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['vault_id'], 'xfiles_img_vault_idx');
            $table->addIndex(['user_id'], 'xfiles_img_user_idx');
        }

        return $schema;
    }
}
