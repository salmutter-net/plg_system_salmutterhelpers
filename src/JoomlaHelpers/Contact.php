<?php

namespace SalmutterNet\JoomlaHelpers;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

final class Contact
{
    public static function byId(int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }

        $db = self::db();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__contact_details'))
            ->where($db->quoteName('id') . ' = ' . $id)
            ->where($db->quoteName('published') . ' = 1');

        return $db->setQuery($query)->loadObject() ?: null;
    }

    public static function byUserId(int $userId): ?object
    {
        if ($userId <= 0) {
            return null;
        }

        $db = self::db();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__contact_details'))
            ->where($db->quoteName('user_id') . ' = ' . $userId)
            ->where($db->quoteName('published') . ' = 1');

        return $db->setQuery($query)->loadObject() ?: null;
    }

    public static function byCategory(
        int $categoryId,
        int $state = 1,
        int $offset = 0,
        int $limit = 0,
        string $orderColumn = 'modified',
        string $orderDirection = 'DESC',
        bool $randomize = false,
    ): array {
        if ($categoryId <= 0) {
            return [];
        }

        $db = self::db();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__contact_details'))
            ->where($db->quoteName('catid') . ' = ' . $categoryId)
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

    private static function db(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }
}
