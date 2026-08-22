<?php

declare(strict_types=1);

namespace OCA\XFiles\Listener;

use OCA\Files_Trashbin\Events\MoveToTrashEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * Disables trashbin for files being classified (moved) into the X-Files vault.
 *
 * When X-Files deletes the original file after a verified import,
 * this listener prevents it from going to the trashbin — achieving
 * a true "move" semantic where the file disappears from the user's
 * visible filesystem completely.
 *
 * The listener is activated via a runtime flag set by ImageService
 * before calling Node::delete().
 *
 * @implements IEventListener<MoveToTrashEvent>
 */
class BypassTrashbinListener implements IEventListener {

    /** @var array<string, bool> Paths flagged for permanent delete */
    private static array $flaggedPaths = [];

    /**
     * Flag a path for permanent deletion (bypass trashbin).
     * Must be called BEFORE Node::delete().
     */
    public static function flagForPermanentDelete(string $internalPath): void {
        self::$flaggedPaths[$internalPath] = true;
    }

    /**
     * Clear a flag (cleanup after operation).
     */
    public static function clearFlag(string $internalPath): void {
        unset(self::$flaggedPaths[$internalPath]);
    }

    public function handle(Event $event): void {
        if (!($event instanceof MoveToTrashEvent)) {
            return;
        }

        $node = $event->getNode();
        $path = $node->getPath();

        if (isset(self::$flaggedPaths[$path])) {
            $event->disableTrashBin();
            unset(self::$flaggedPaths[$path]);
        }
    }
}
