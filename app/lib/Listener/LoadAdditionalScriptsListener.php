<?php

declare(strict_types=1);

namespace OCA\XFiles\Listener;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\XFiles\AppInfo\Application;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * Injects the X-Files init script into the Files app.
 * This registers the "Send to X-Files" FileAction in the context menu.
 *
 * @implements IEventListener<LoadAdditionalScriptsEvent>
 */
class LoadAdditionalScriptsListener implements IEventListener {

    public function handle(Event $event): void {
        if (!($event instanceof LoadAdditionalScriptsEvent)) {
            return;
        }

        Util::addInitScript(Application::APP_ID, 'xfiles-init');
    }
}
