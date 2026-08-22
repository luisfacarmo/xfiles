<?php

declare(strict_types=1);

/** @var array $_ */

use OCP\Util;

?>

<div id="xfiles-admin-settings" class="section">
    <h2>X-Files</h2>
    <p class="settings-hint"><?php p($l->t('Configure vault settings for all users.')); ?></p>

    <div class="xfiles-settings-group">
        <h3><?php p($l->t('Access')); ?></h3>
        <p>
            <label for="xfiles-enabled-groups"><?php p($l->t('Limit to groups (empty = all users)')); ?></label>
            <input type="text" id="xfiles-enabled-groups" name="enabled_groups"
                   value="<?php p($_['enabled_groups']); ?>"
                   placeholder="<?php p($l->t('e.g. admin, photos-team')); ?>">
        </p>
    </div>

    <div class="xfiles-settings-group">
        <h3><?php p($l->t('Limits')); ?></h3>
        <p>
            <label for="xfiles-max-file-size"><?php p($l->t('Maximum file size (MB)')); ?></label>
            <input type="number" id="xfiles-max-file-size" name="max_file_size_mb"
                   value="<?php p($_['max_file_size_mb']); ?>" min="1" max="500">
        </p>
        <p>
            <label for="xfiles-default-timeout"><?php p($l->t('Default auto-lock timeout (seconds)')); ?></label>
            <select id="xfiles-default-timeout" name="default_auto_lock_seconds">
                <option value="60" <?php if ($_['default_auto_lock_seconds'] === 60) p('selected'); ?>><?php p($l->t('1 minute')); ?></option>
                <option value="300" <?php if ($_['default_auto_lock_seconds'] === 300) p('selected'); ?>><?php p($l->t('5 minutes')); ?></option>
                <option value="900" <?php if ($_['default_auto_lock_seconds'] === 900) p('selected'); ?>><?php p($l->t('15 minutes')); ?></option>
                <option value="1800" <?php if ($_['default_auto_lock_seconds'] === 1800) p('selected'); ?>><?php p($l->t('30 minutes')); ?></option>
                <option value="3600" <?php if ($_['default_auto_lock_seconds'] === 3600) p('selected'); ?>><?php p($l->t('1 hour')); ?></option>
                <option value="0" <?php if ($_['default_auto_lock_seconds'] === 0) p('selected'); ?>><?php p($l->t('Never')); ?></option>
            </select>
        </p>
    </div>

    <div class="xfiles-settings-group">
        <h3><?php p($l->t('Recovery')); ?></h3>
        <p>
            <input type="checkbox" id="xfiles-allow-nc-recovery" name="allow_nc_password_recovery"
                   <?php if ($_['allow_nc_password_recovery'] === 'true') p('checked'); ?>>
            <label for="xfiles-allow-nc-recovery"><?php p($l->t('Allow users to reset vault password using their Nextcloud account password')); ?></label>
        </p>
    </div>

    <div class="xfiles-settings-group">
        <h3><?php p($l->t('Statistics')); ?></h3>
        <table>
            <tr>
                <td><?php p($l->t('Active vaults')); ?></td>
                <td><strong><?php p($_['stats']['vault_count']); ?></strong></td>
            </tr>
            <tr>
                <td><?php p($l->t('Total images')); ?></td>
                <td><strong><?php p($_['stats']['image_count']); ?></strong></td>
            </tr>
            <tr>
                <td><?php p($l->t('Total storage used')); ?></td>
                <td><strong><?php p(Util::humanFileSize($_['stats']['total_size'])); ?></strong></td>
            </tr>
        </table>
    </div>
</div>

<style>
#xfiles-admin-settings .xfiles-settings-group {
    margin-top: 20px;
}
#xfiles-admin-settings .xfiles-settings-group h3 {
    font-weight: 600;
    margin-bottom: 8px;
}
#xfiles-admin-settings .xfiles-settings-group p {
    margin: 8px 0;
}
#xfiles-admin-settings .xfiles-settings-group label {
    display: inline-block;
    min-width: 280px;
}
#xfiles-admin-settings table {
    margin-top: 8px;
}
#xfiles-admin-settings table td {
    padding: 4px 16px 4px 0;
}
</style>
