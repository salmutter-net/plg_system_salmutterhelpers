<?php

namespace SalmutterNet\JoomlaHelpers;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Component\Content\Administrator\Extension\ContentComponent;
use Joomla\Database\DatabaseInterface;

final class Content
{
    public static function articleById(int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }

        $db = self::db();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = ' . $id)
            ->where($db->quoteName('state') . ' = 1');

        return $db->setQuery($query)->loadObject() ?: null;
    }

    /**
     * @param int|int[] $categoryId
     */
    public static function articlesByCategory(
        int|array $categoryId,
        int $state = ContentComponent::CONDITION_PUBLISHED,
        int $offset = 0,
        int $limit = 0,
        array $excludedIds = [],
        bool $includeSubcategories = true,
        int $maxCategoryLevels = 10,
        string $orderColumn = 'modified',
        string $orderDirection = 'DESC',
        array $tagIds = [],
        string $filterByDate = 'off',
        string $dateField = 'a.created',
        string $dateRangeStart = '',
        string $dateRangeEnd = '',
        string $dateRelative = '',
    ): array {
        $app = Factory::getApplication();
        $factory = $app->bootComponent('com_content')->getMVCFactory();
        $model = $factory->createModel('Articles', 'Site', ['ignore_request' => true]);

        $appParams = ComponentHelper::getParams('com_content');
        $model->setState('params', $appParams);
        $model->setState('list.start', $offset);
        $model->setState('list.limit', $limit);
        $model->setState('filter.published', $state);

        // Date filtering
        $model->setState('filter.date_filtering', $filterByDate);
        $model->setState('filter.date_field', $dateField);

        if ($filterByDate === 'range') {
            $model->setState('filter.start_date_range', $dateRangeStart);
            $model->setState('filter.end_date_range', $dateRangeEnd);
        }

        if ($filterByDate === 'relative') {
            $model->setState('filter.relative_date', $dateRelative);
        }

        // Exclusions
        if ($excludedIds !== []) {
            $model->setState('filter.article_id', $excludedIds);
        }
        $model->setState('filter.article_id.include', false);

        // Category + subcategories
        if ($categoryId !== 0 && $categoryId !== []) {
            $catIds = is_array($categoryId) ? $categoryId : [$categoryId];

            if ($includeSubcategories) {
                $catModel = $factory->createModel('Categories', 'Site', ['ignore_request' => true]);
                $catModel->setState('params', $appParams);
                $catModel->setState('filter.get_children', $maxCategoryLevels);
                $catModel->setState('filter.published', $state);
                $catModel->setState('filter.parentId', is_array($categoryId) ? $categoryId[0] : $categoryId);

                // Categories model requires a non-CLI application context
                $cliApp = null;
                if (Factory::getApplication()->isClient('cli')) {
                    $cliApp = Factory::$application;
                    Factory::$application = Factory::getContainer()->get(AdministratorApplication::class);
                }

                try {
                    $children = $catModel->getItems(true);
                } finally {
                    if ($cliApp !== null) {
                        Factory::$application = $cliApp;
                    }
                }

                if ($children) {
                    $parentLevel = $catModel->getParent()->level;
                    foreach ($children as $child) {
                        if (($child->level - $parentLevel) <= $maxCategoryLevels) {
                            $catIds[] = (int) $child->id;
                        }
                    }
                }

                $catIds = array_values(array_unique($catIds));
            }

            $model->setState('filter.category_id', $catIds);
        }

        // Tags
        if ($tagIds !== []) {
            $model->setState('filter.tag', $tagIds);
        }

        // Access
        $showNoAuth = ComponentHelper::getParams('com_content')->get('show_noauth');
        $model->setState('filter.access', !$showNoAuth);

        // Ordering
        if ($orderColumn === 'random') {
            $model->setState('list.ordering', self::db()->getQuery(true)->rand());
        } else {
            $model->setState('list.ordering', 'a.' . $orderColumn);
            $model->setState('list.direction', $orderDirection);
        }

        $items = $model->getItems();

        return is_array($items) ? $items : [];
    }

    public static function categoryById(int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }

        $db = self::db();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('id') . ' = ' . $id)
            ->where($db->quoteName('published') . ' = 1');

        return $db->setQuery($query)->loadObject() ?: null;
    }

    public static function subcategories(
        int $parentId,
        int $state = 1,
        int $offset = 0,
        int $limit = 0,
        string $orderColumn = 'lft',
        string $orderDirection = 'ASC',
        bool $randomize = false,
    ): array {
        if ($parentId <= 0) {
            return [];
        }

        $db = self::db();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('parent_id') . ' = ' . $parentId)
            ->where($db->quoteName('published') . ' = ' . $state);

        if ($randomize) {
            $query->order($query->rand());
        } else {
            $query->order($db->quoteName($orderColumn) . ' ' . $orderDirection);
        }

        if ($limit > 0) {
            $query->setLimit($limit, $offset);
        }

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    public static function articleIsInCategory(object $article, int $categoryId): bool
    {
        return (int) ($article->catid ?? 0) === $categoryId
            || (int) ($article->parent_id ?? 0) === $categoryId;
    }

    public static function cleanImageUrl(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        $clean = HTMLHelper::cleanImageURL($value);

        return ($clean->url ?? '') !== '' ? $clean->url : '';
    }

    /**
     * Wraps {loadposition}, {loadmodule}, and {loadmoduleid} tags with closing/reopening
     * DOM elements so injected modules can break out of a nested wrapper structure.
     *
     * Each entry in $wrappers defines one nesting level:
     *   ['tag' => 'div', 'class' => 'article-body']   → <div class="article-body">
     *   ['tag' => 'div']                               → <div>
     *
     * The closing side is generated automatically from the same list (innermost first).
     *
     * Example — two-level wrapper matching firstoptiker26 article layout:
     *   Content::breakBodyText($text, [
     *       ['tag' => 'div', 'class' => 'article-body'],
     *       ['tag' => 'div', 'class' => 'bodytext'],
     *   ]);
     *
     * Example — single wrapper (e.g. category description):
     *   Content::breakBodyText($text, [
     *       ['tag' => 'div', 'class' => 'category__description'],
     *   ]);
     *
     * @param string                          $text     Article/category text to process.
     * @param array<array{tag:string,class?:string}> $wrappers Ordered list of wrapper elements (outermost first).
     */
    public static function breakBodyText(string $text, array $wrappers = [
        ['tag' => 'div', 'class' => 'article-body'],
        ['tag' => 'div', 'class' => 'bodytext'],
    ]): string
    {
        if ($wrappers === []) {
            return $text;
        }

        // Build the closing fragment (innermost element first)
        $closing = '';
        foreach (array_reverse($wrappers) as $w) {
            $closing .= '</' . htmlspecialchars($w['tag'], ENT_QUOTES) . '>';
        }

        // Build the reopening fragment (outermost element first)
        $reopening = '';
        foreach ($wrappers as $w) {
            $tag   = htmlspecialchars($w['tag'], ENT_QUOTES);
            $class = isset($w['class']) && $w['class'] !== '' ? ' class="' . htmlspecialchars($w['class'], ENT_QUOTES) . '"' : '';
            $reopening .= '<' . $tag . $class . '>';
        }

        $patterns = [
            '{loadposition '  => '/{loadposition\s(.*?)}/i',
            '{loadmodule '    => '/{loadmodule\s(.*?)}/i',
            '{loadmoduleid '  => '/{loadmoduleid\s([1-9][0-9]*)}/i',
        ];

        foreach ($patterns as $needle => $pattern) {
            if (!str_contains($text, $needle)) {
                continue;
            }
            preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $replacement = $closing . trim($match[0]) . $reopening;
                $text = substr_replace($text, $replacement, strpos($text, $match[0]), strlen($match[0]));
            }
        }

        return $text;
    }

    private static function db(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }
}
