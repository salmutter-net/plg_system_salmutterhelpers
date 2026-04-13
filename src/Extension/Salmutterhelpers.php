<?php

/**
 * @package     SalmutterNet.Plugin
 * @subpackage  System.salmutterhelpers
 */

namespace SalmutterNet\Plugin\System\Salmutterhelpers\Extension;

use Joomla\CMS\Event\Application\AfterInitialiseEvent;
use Joomla\CMS\Factory;
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

        $localHelpersPathsParam = (string) $this->params->get('local_helpers_paths', '');
        $localHelpersPaths = [];

        if ($localHelpersPathsParam !== '') {
            $parts = preg_split('/[\r\n,]+/', $localHelpersPathsParam) ?: [];
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $localHelpersPaths[] = $part;
                }
            }
        }

        if ($localHelpersPaths === []) {
            $app = Factory::getApplication();
            $templateName = '';

            if (method_exists($app, 'getTemplate')) {
                $template = $app->getTemplate(true);
                if (is_object($template)) {
                    $templateName = (string) ($template->template ?? '');
                } elseif (is_string($template)) {
                    $templateName = $template;
                }
            }

            if ($templateName !== '') {
                $localHelpersPaths[] = 'templates/' . $templateName . '/local-helpers';
            }

            $localHelpersPaths[] = 'local-helpers';
        }

        foreach ($localHelpersPaths as $basePath) {
            $basePath = trim($basePath);
            if ($basePath === '') {
                continue;
            }

            $absoluteBasePath = str_starts_with($basePath, '/') ? $basePath : (JPATH_ROOT . '/' . ltrim($basePath, '/'));
            $absoluteBasePath = rtrim($absoluteBasePath, '/\\');

            $localHelpersComposerAutoload = $absoluteBasePath . '/vendor/autoload.php';
            if (is_file($localHelpersComposerAutoload)) {
                require_once $localHelpersComposerAutoload;
            }

            $localHelpersSrc = $absoluteBasePath . '/src';
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
        }

        $sharedPrefix = 'SalmutterNet\\JoomlaHelpers\\';
        $sharedBaseDir = rtrim(__DIR__ . '/../JoomlaHelpers', '/\\') . DIRECTORY_SEPARATOR;
        spl_autoload_register(static function (string $class) use ($sharedPrefix, $sharedBaseDir): void {
            $len = strlen($sharedPrefix);
            if (strncmp($sharedPrefix, $class, $len) !== 0) {
                return;
            }

            $relativeClass = substr($class, $len);
            $file = $sharedBaseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

            if (is_file($file)) {
                require $file;
            }
        });

        $loaded = true;
    }
}
