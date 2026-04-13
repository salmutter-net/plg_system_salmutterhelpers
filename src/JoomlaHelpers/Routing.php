<?php

namespace SalmutterNet\JoomlaHelpers;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Contact\Site\Helper\RouteHelper as ContactRouteHelper;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Component\Tags\Site\Helper\RouteHelper as TagsRouteHelper;

final class Routing
{
    public static function articleById(int $id, int $catId = 0, string $alias = '', string $language = '*', ?int $itemId = null): string
    {
        $slug = $alias !== '' ? ($id . ':' . $alias) : (string) $id;
        $route = RouteHelper::getArticleRoute($slug, $catId, $language);

        if ($itemId !== null) {
            $route .= (str_contains($route, '?') ? '&' : '?') . 'Itemid=' . $itemId;
        }

        return Route::link('site', $route, true, 0, true);
    }

    public static function articleByObject(object $item): string
    {
        $id = (int) ($item->id ?? 0);
        $catId = (int) ($item->catid ?? 0);
        $alias = (string) ($item->alias ?? '');
        $language = (string) ($item->language ?? '*');

        $slug = $alias !== '' ? ($id . ':' . $alias) : (string) $id;
        $route = RouteHelper::getArticleRoute($slug, $catId, $language);

        if (Factory::getApplication()->getName() !== 'site') {
            if (Factory::getApplication()->getName() === 'cli') {
                $itemId = self::resolveItemIdForArticleInCli($id, $catId);
                if ($itemId !== null) {
                    $route .= (str_contains($route, '?') ? '&' : '?') . 'Itemid=' . $itemId;
                }
            }

            return Route::link('site', $route, true, 0, true);
        }

        return Route::_($route);
    }

    public static function tagById(int $id, string $language = '*', ?int $itemId = null): string
    {
        $route = TagsRouteHelper::getComponentTagRoute((string) $id, $language);

        if ($itemId !== null) {
            $route .= (str_contains($route, '?') ? '&' : '?') . 'Itemid=' . $itemId;
        }

        return Route::link('site', $route, true, 0, true);
    }

    public static function tagByObject(object $tag): string
    {
        $id = (int) ($tag->id ?? 0);
        $alias = (string) ($tag->alias ?? '');
        $language = (string) ($tag->language ?? '*');

        $slug = $alias !== '' ? ($id . ':' . $alias) : (string) $id;
        $route = TagsRouteHelper::getComponentTagRoute($slug, $language);

        if (Factory::getApplication()->getName() !== 'site') {
            return Route::link('site', $route, true, 0, true);
        }

        return Route::_($route);
    }

    public static function categoryById(int $id, string $language = '*', ?int $itemId = null): string
    {
        $route = RouteHelper::getCategoryRoute($id, $language);

        if ($itemId !== null) {
            $route .= (str_contains($route, '?') ? '&' : '?') . 'Itemid=' . $itemId;
        }

        return Route::link('site', $route, true, 0, true);
    }

    public static function categoryByObject(object $category): string
    {
        $id = (int) ($category->id ?? 0);
        $language = (string) ($category->language ?? '*');
        $route = RouteHelper::getCategoryRoute($id, $language);

        if (Factory::getApplication()->getName() !== 'site') {
            return Route::link('site', $route, true, 0, true);
        }

        return Route::_($route);
    }

    public static function contactById(int $id, int $catId = 0, string $language = '*', ?int $itemId = null): string
    {
        $route = ContactRouteHelper::getContactRoute($id, $catId, $language);

        if ($itemId !== null) {
            $route .= (str_contains($route, '?') ? '&' : '?') . 'Itemid=' . $itemId;
        }

        return Route::link('site', $route, true, 0, true);
    }

    public static function contactByObject(object $contact): string
    {
        $id = (int) ($contact->id ?? 0);
        $catId = (int) ($contact->catid ?? 0);
        $language = (string) ($contact->language ?? '*');
        $route = ContactRouteHelper::getContactRoute($id, $catId, $language);

        if (Factory::getApplication()->getName() !== 'site') {
            return Route::link('site', $route, true, 0, true);
        }

        return Route::_($route);
    }

    public static function contactCategoryById(int $id, string $language = '*', ?int $itemId = null): string
    {
        $route = ContactRouteHelper::getCategoryRoute($id, $language);

        if ($itemId !== null) {
            $route .= (str_contains($route, '?') ? '&' : '?') . 'Itemid=' . $itemId;
        }

        return Route::link('site', $route, true, 0, true);
    }

    public static function contactCategoryByObject(object $category): string
    {
        $id = (int) ($category->id ?? 0);
        $language = (string) ($category->language ?? '*');
        $route = ContactRouteHelper::getCategoryRoute($id, $language);

        if (Factory::getApplication()->getName() !== 'site') {
            return Route::link('site', $route, true, 0, true);
        }

        return Route::_($route);
    }

    public static function canonicalArticleUrl(object $article): string
    {
        $id = (int) ($article->id ?? 0);
        $catId = (int) ($article->catid ?? 0);
        $language = (string) ($article->language ?? '*');

        $url = rtrim(Uri::root(), '/') . Route::_(RouteHelper::getArticleRoute((string) $id, $catId, $language));

        $urlsJson = (string) ($article->urls ?? '');
        if ($urlsJson !== '') {
            try {
                $urls = json_decode($urlsJson, false, 512, JSON_THROW_ON_ERROR);
                if (is_object($urls) && !empty($urls->urlc)) {
                    $url = (string) $urls->urlc;
                }
            } catch (\JsonException) {
            }
        }

        return $url;
    }

    public static function redirectToCanonicalArticleUrl(object $article): void
    {
        $target = self::canonicalArticleUrl($article);
        if (urldecode(Uri::current()) !== urldecode($target)) {
            header('HTTP/1.1 301 Moved Permanently');
            header('Location: ' . $target);
            exit;
        }
    }

    private static function resolveItemIdForArticleInCli(int $articleId, int $catId): ?int
    {
        $cliApp = Factory::$application;

        try {
            $siteApp = Factory::getContainer()->get(SiteApplication::class);
            Factory::$application = $siteApp;

            $menu = Factory::getApplication()->getMenu();
            $menuItems = $menu->getMenu();

            foreach ($menuItems as $menuItem) {
                if (!is_object($menuItem)) {
                    continue;
                }

                $query = $menuItem->query ?? null;
                if (is_array($query)) {
                    $option = (string) ($query['option'] ?? '');
                    $view = (string) ($query['view'] ?? '');
                    $id = (int) ($query['id'] ?? 0);

                    if ($option === 'com_content' && $view === 'article' && $id === $articleId) {
                        return (int) ($menuItem->id ?? 0) ?: null;
                    }

                    if ($option === 'com_content' && $view === 'category' && $id === $catId) {
                        return (int) ($menuItem->id ?? 0) ?: null;
                    }
                }

                $link = (string) ($menuItem->link ?? '');
                if ($link !== '') {
                    if (str_contains($link, 'option=com_content') && str_contains($link, 'view=article') && str_contains($link, 'id=' . $articleId)) {
                        return (int) ($menuItem->id ?? 0) ?: null;
                    }
                    if (str_contains($link, 'option=com_content') && str_contains($link, 'view=category') && str_contains($link, 'id=' . $catId)) {
                        return (int) ($menuItem->id ?? 0) ?: null;
                    }
                }
            }
        } finally {
            Factory::$application = $cliApp;
        }

        return null;
    }
}
