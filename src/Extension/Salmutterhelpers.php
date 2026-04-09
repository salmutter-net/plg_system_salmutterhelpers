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

        $autoload = JPATH_ROOT . '/../vendor/autoload.php';

        if (is_file($autoload)) {
            require_once $autoload;
        }

        $loaded = true;
    }
}
