<?php

/**
 * @package     SalmutterNet.Plugin
 * @subpackage  System.salmutterhelpers
 */

namespace SalmutterNet\Plugin\System\Salmutterhelpers\Extension;

use Joomla\CMS\Event\Application\AfterInitialiseEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class Salmutterhelpers extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterInitialise' => 'onAfterInitialise',
        ];
    }

    public function onAfterInitialise(AfterInitialiseEvent $event): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $localHelpersComposerAutoload = JPATH_ROOT . '/local-helpers/vendor/autoload.php';
        if (is_file($localHelpersComposerAutoload)) {
            require_once $localHelpersComposerAutoload;
            $loaded = true;
            return;
        }

        $localHelpersSrc = JPATH_ROOT . '/local-helpers/src';
        if (is_dir($localHelpersSrc)) {
            $prefix = 'Project\\';
            $baseDir = rtrim($localHelpersSrc, '/\\') . DIRECTORY_SEPARATOR;

            spl_autoload_register(static function (string $class) use ($prefix, $baseDir): void {
                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len) !== 0) {
                    return;
                }

                $relativeClass = substr($class, $len);
                $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

                if (is_file($file)) {
                    require $file;
                }
            });
        }

        $loaded = true;
    }
}
