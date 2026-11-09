<?php
namespace Studip\Build;

use Composer\Script\Event;

/**
 * This is a composer hook that excludes the traceable studip pdo helper file
 * that relies on a class that is only available in dev mode.
 *
 * @author Jan-Hendrik Willms <tleilax+studip@gmail.com>
 * @license GPL2 or any later version
 */
final class ComposerHooks
{
    public static function configureAttributeCollector(Event $event): void
    {
        if ($event->isDevMode()) {
            return;
        }

        $package = $event->getComposer()->getPackage();
        $extra = $package->getExtra();

        $extra['composer-attribute-collector']['exclude'][] = 'lib/classes/Debug/TraceableStudipPDO.php';

        $package->setExtra($extra);
    }
}
