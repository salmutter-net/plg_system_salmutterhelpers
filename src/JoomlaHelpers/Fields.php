<?php

namespace SalmutterNet\JoomlaHelpers;

use Joomla\CMS\Factory;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Registry\Registry;

final class Fields
{
    /**
     * Available Joomla content contexts for custom fields.
     */
    public const CONTEXT_ARTICLE          = 'com_content.article';
    public const CONTEXT_CONTENT_CATEGORY = 'com_content.category';
    public const CONTEXT_CONTACT          = 'com_contact.contact';
    public const CONTEXT_CONTACT_CATEGORY = 'com_contact.category';
    public const CONTEXT_NEWSFEEDS        = 'com_newsfeeds.newsfeed';
    public const CONTEXT_NEWSFEEDS_CATEGORY = 'com_newsfeeds.category';
    public const CONTEXT_BANNER           = 'com_banners.banner';
    public const CONTEXT_BANNER_CATEGORY  = 'com_banners.category';
    public const CONTEXT_USER             = 'com_users.user';

    /**
     * Create a new custom field in the #__fields table.
     *
     * @param string $title         Human-readable label shown in the UI.
     * @param string $context       One of the CONTEXT_* constants, e.g. self::CONTEXT_ARTICLE.
     * @param string $type          Field type: 'text', 'textarea', 'integer', 'url', 'email',
     *                              'list', 'checkboxes', 'radio', 'media', 'calendar',
     *                              'color', 'editor', 'sql', 'subform', etc.
     * @param string $name          URL-safe machine name. Auto-derived from $title when empty.
     * @param string $defaultValue  Pre-filled default value stored for every item.
     * @param string $label         Label override (falls back to $title).
     * @param string $note          Optional hint text shown below the field.
     * @param array  $fieldparams   Type-specific parameters (e.g. ['options' => [...]]).
     * @param array  $params        Generic field parameters (e.g. ['hint' => '', 'required' => 0]).
     * @param int    $groupId       Field group id; 0 = no group.
     * @param int    $state         1 = published, 0 = unpublished.
     * @param string $language      Language tag, e.g. '*', 'en-GB'.
     * @param int    $access        Joomla access level id; 1 = public.
     * @param int    $ordering      Ordering position within the group/context.
     *
     * @return int|false  The new field id on success, false on failure.
     */
    public static function create(
    string $title,
    string $context = self::CONTEXT_ARTICLE,
    string $type = 'text',
    string $name = '',
    string $defaultValue = '',
    string $label = '',
    string $note = '',
    array $fieldparams = [],
    array $params = [],
    int $groupId = 0,
    int $state = 1,
    string $language = '*',
    int $access = 1,
    int $ordering = 0
    ): int|false {
        $db   = Factory::getDbo();
        $date = Factory::getDate()->toSql();

        $paramsRegistry      = new Registry($params);
        $fieldparamsRegistry = new Registry($fieldparams);

        $data = (object) [
        'id'                => 0,
        'title'             => $title,
        'name'              => $name,
        'label'             => $label !== '' ? $label : $title,
        'default_value'     => $defaultValue,
        'type'              => $type,
        'note'              => $note,
        'context'           => $context,
        'group_id'          => $groupId,
        'state'             => $state,
        'ordering'          => $ordering,
        'language'          => $language,
        'access'            => $access,
        'params'            => $paramsRegistry->toString(),
        'fieldparams'       => $fieldparamsRegistry->toString(),
        'created_time'      => $date,
        'modified_time'     => $date,
        'created_user_id'   => Factory::getUser()->id,
        'modified_by'       => Factory::getUser()->id,
        ];

        try {
            $db->insertObject('#__fields', $data, 'id');
        } catch (\RuntimeException $e) {
            return false;
        }

        return (int) $data->id > 0 ? (int) $data->id : false;
    }


    /**
     * Get the raw value of a custom field.
     *
     * @param string $context  e.g. 'com_content.article', 'com_content.category', 'com_contact.contact'
     * @return string|false  Raw field value or false if not found / empty.
     */
    public static function getValue(object $item, string $fieldName, string $context = 'com_content.article'): string|false
    {
        $fields = self::resolveFields($item, $context);

        if ($fields === null || !isset($fields[$fieldName]) || $fields[$fieldName] === '') {
            return false;
        }

        return $fields[$fieldName];
    }

    /**
     * Get a custom field value decoded as an associative array (for repeatable / JSON fields).
     */
    public static function getAsArray(object $item, string $fieldName, string $context = 'com_content.article'): ?array
    {
        $raw = self::getValue($item, $fieldName, $context);

        if ($raw === false || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Get the full field object (includes label, type, params, rawvalue, value, etc.).
     */
    public static function getObject(object $item, string $fieldName, string $context = 'com_content.article'): ?object
    {
        $jcfields = self::loadJcfields($item, $context);

        foreach ($jcfields as $field) {
            if (($field->name ?? '') === $fieldName) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Check whether a custom field has a non-empty value (replaces fieldCheck).
     */
    public static function hasValue(object $item, string $fieldName, string $context = 'com_content.article'): bool
    {
        return self::getValue($item, $fieldName, $context) !== false;
    }

    /**
     * Check if a field definition exists by title and context.
     *
     * @param string $title   The field title (label) to search for.
     * @param string $context One of the CONTEXT_* constants.
     * @return int|false      The field ID if found, false otherwise.
     */
    public static function exists(string $title, string $context = self::CONTEXT_ARTICLE): int|false
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
        ->select($db->quoteName('id'))
        ->from($db->quoteName('#__fields'))
        ->where($db->quoteName('title') . ' = ' . $db->quote($title))
        ->where($db->quoteName('context') . ' = ' . $db->quote($context))
        ->where($db->quoteName('state') . ' = 1');

        $id = $db->setQuery($query)->loadResult();

        return $id ? (int) $id : false;
    }

    /**
     * Build a name => rawvalue map from the item's custom fields.
     *
     * @return array<string, string>|null
     */
    private static function resolveFields(object $item, string $context): ?array
    {
        $jcfields = self::loadJcfields($item, $context);

        if ($jcfields === []) {
            return null;
        }

        $map = [];
        foreach ($jcfields as $field) {
            $map[$field->name] = (string) ($field->rawvalue ?? '');
        }

        return $map;
    }

    /**
     * @return object[]
     */
    private static function loadJcfields(object $item, string $context): array
    {
        if (isset($item->jcfields) && is_array($item->jcfields)) {
            return $item->jcfields;
        }

        $fields = FieldsHelper::getFields($context, $item, true);

        return is_array($fields) ? $fields : [];
    }
}
