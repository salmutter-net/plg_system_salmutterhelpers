<?php

namespace SalmutterNet\JoomlaHelpers;

use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;

final class Fields
{
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
