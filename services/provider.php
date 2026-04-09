<?php

/**
 * @package     SalmutterNet.Plugin
 * @subpackage  System.salmutterhelpers
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use SalmutterNet\Plugin\System\Salmutterhelpers\Extension\Salmutterhelpers;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $plugin = new Salmutterhelpers(
                    (array) PluginHelper::getPlugin('system', 'salmutterhelpers')
                );

                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
