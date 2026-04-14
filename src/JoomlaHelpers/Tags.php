<?php

namespace SalmutterNet\JoomlaHelpers;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\TagsHelper;
use Joomla\Database\DatabaseInterface;

final class Tags
{
    public static function byId(int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__tags'))
            ->where($db->quoteName('id') . ' = ' . $id)
            ->where($db->quoteName('published') . ' = 1');

        return $db->setQuery($query)->loadObject() ?: null;
    }

    public static function forArticle(int $articleId): array
    {
        return self::forItem('com_content.article', $articleId);
    }

    public static function forCategory(int $categoryId): array
    {
        return self::forItem('com_content.category', $categoryId);
    }

    public static function forContact(int $contactId): array
    {
        return self::forItem('com_contact.contact', $contactId);
    }

    /**
     * Get tags for any content item by its type context and ID.
     *
     * @param string $contentType  e.g. 'com_content.article', 'com_content.category', 'com_contact.contact'
     */
    public static function forItem(string $contentType, int $itemId): array
    {
        if ($itemId <= 0 || $contentType === '') {
            return [];
        }

        $tags = (new TagsHelper())->getItemTags($contentType, $itemId);

        return is_array($tags) ? $tags : (is_iterable($tags) ? iterator_to_array($tags) : []);
    }

    /**
     * Check whether a content item has a specific tag (by alias or ID).
     */
    public static function itemHasTag(string $contentType, int $itemId, string|int $tagAliasOrId): bool
    {
        $tags = self::forItem($contentType, $itemId);

        foreach ($tags as $tag) {
            if (is_int($tagAliasOrId) && (int) ($tag->id ?? 0) === $tagAliasOrId) {
                return true;
            }

            if (is_string($tagAliasOrId) && ($tag->alias ?? '') === $tagAliasOrId) {
                return true;
            }
        }

        return false;
    }
}
