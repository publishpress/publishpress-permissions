<?php

namespace PublishPress\Permissions\Mcp;

/**
 * Strict JSON input validation for Permissions abilities.
 *
 * @package PublishPressPermissions
 * @since 1.1.0
 */

\defined('ABSPATH') || exit;

class Input
{
    /**
     * Normalize JSON objects only after checking their container type.
     *
     * @since 1.1.0
     * @param mixed $value Decoded JSON value.
     * @return array|WP_Error
     */
    public static function object($value)
    {
        if ($value instanceof \stdClass) {
            return get_object_vars($value);
        }
        if (is_array($value) && ($value === [] || array_keys($value) !== range(0, count($value) - 1))) {
            return $value;
        }
        return self::error();
    }

    /**
     * Present reviewed native storage values using the corresponding writable JSON types.
     * This never normalizes incoming tool arguments: validate() remains strict.
     *
     * @since 1.1.0
     * @param mixed $value Native stored value.
     * @param array $schema Writable schema.
     * @return mixed|WP_Error
     */
    public static function from_native($value, array $schema)
    {
        $types = (array) ($schema['type'] ?? []);
        if (in_array('boolean', $types, true) && in_array($value, [null, '', 0, 1, '0', '1', false, true], true)) {
            $value = (bool) $value;
        } elseif (in_array('string', $types, true) && is_int($value)) {
            $value = (string) $value;
        } elseif (in_array('integer', $types, true) && is_string($value) && preg_match('/^-?[0-9]+$/D', $value)) {
            $number = filter_var($value, FILTER_VALIDATE_INT);
            if (false !== $number) {
                $value = $number;
            }
        } elseif (in_array('array', $types, true) && in_array($value, [null, ''], true) && ! in_array('null', $types, true)) {
            $value = [];
        } elseif (in_array('array', $types, true) && is_array($value)) {
            $items = [];
            foreach ($value as $item) {
                $clean = self::from_native($item, $schema['items']);
                if (is_wp_error($clean)) {
                    return $clean;
                }
                $items[] = $clean;
            }
            $value = $items;
        } elseif (in_array('object', $types, true) && (is_array($value) || $value instanceof \stdClass)) {
            $row = [];
            foreach ((array) $value as $key => $item) {
                if (! isset($schema['properties'][$key])) {
                    return self::error();
                }
                $row[$key] = self::from_native($item, $schema['properties'][$key]);
                if (is_wp_error($row[$key])) {
                    return $row[$key];
                }
            }
            $value = (object) $row;
        }
        return self::validate($value, $schema);
    }

    /** @since 1.1.0 @param array $row Reviewed native row. @param array $definitions Field definitions. @return array|WP_Error */
    public static function native_row(array $row, array $definitions)
    {
        $safe = array_intersect_key($row, $definitions);
        foreach ($safe as $key => $value) {
            $safe[$key] = self::from_native($value, $definitions[$key]['schema']);
            if (is_wp_error($safe[$key])) {
                return $safe[$key];
            }
        }
        return $safe;
    }

    /**
     * Validate without WordPress REST's scalar coercion.
     *
     * @since 1.1.0
     * @param mixed $value Input value.
     * @param array $schema JSON schema.
     * @return mixed|WP_Error
     */
    public static function validate($value, array $schema)
    {
        $type = $schema['type'] ?? '';
        if (is_array($type)) {
            foreach ($type as $variant) {
                $candidate = self::validate($value, array_merge($schema, ['type' => $variant]));
                if (! is_wp_error($candidate)) {
                    return $candidate;
                }
            }
            return self::error();
        }
        if ('null' === $type) {
            return null === $value ? null : self::error();
        }

        if ('object' === $type) {
            $value = self::object($value);
            if (is_wp_error($value)) {
                return $value;
            }
            $properties = (array) ($schema['properties'] ?? []);
            if (array_diff(array_keys($value), array_keys($properties))) {
                return self::error();
            }
            foreach ($schema['required'] ?? [] as $key) {
                if (! array_key_exists($key, $value)) {
                    return self::error();
                }
            }
            foreach ($value as $key => $child) {
                $value[$key] = self::validate($child, $properties[$key]);
                if (is_wp_error($value[$key])) {
                    return $value[$key];
                }
            }
        } elseif ('array' === $type) {
            if (! is_array($value) || ($value !== [] && array_keys($value) !== range(0, count($value) - 1))) {
                return self::error();
            }
            if (count($value) < ($schema['minItems'] ?? 0) || count($value) > ($schema['maxItems'] ?? 100)) {
                return self::error();
            }
            foreach ($value as $key => $child) {
                $value[$key] = self::validate($child, $schema['items']);
                if (is_wp_error($value[$key])) {
                    return $value[$key];
                }
            }
        } elseif (
            ('string' === $type && ! is_string($value)) ||
            ('boolean' === $type && ! is_bool($value)) ||
            ('integer' === $type && ! is_int($value)) ||
            ('number' === $type && ! is_int($value) && ! is_float($value)) ||
            ! in_array($type, ['string', 'boolean', 'integer', 'number'], true)
        ) {
            return self::error();
        }
        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            return self::error();
        }
        if (is_string($value)) {
            $length = preg_match_all('/./us', $value);
            if (false === $length || $length < ($schema['minLength'] ?? 0) || $length > ($schema['maxLength'] ?? 100000)) {
                return self::error();
            }
            if (isset($schema['pattern']) && 1 !== preg_match('~' . $schema['pattern'] . '~D', $value)) {
                return self::error();
            }
        }
        if (is_float($value) && ! is_finite($value)) {
            return self::error();
        }
        if ((is_int($value) || is_float($value)) && ($value < ($schema['minimum'] ?? -PHP_INT_MAX) || $value > ($schema['maximum'] ?? PHP_INT_MAX))) {
            return self::error();
        }
        return $value;
    }

    /**
     * Build a closed JSON object schema.
     *
     * @since 1.1.0
     * @param array $properties Properties.
     * @param array $required Required names.
     * @return array
     */
    public static function schema(array $properties, array $required = [])
    {
        return ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false];
    }

    /**
     * Return a translated error without echoing submitted data.
     *
     * @since 1.1.0
     * @return WP_Error
     */
    public static function error()
    {
        return new \WP_Error('presspermit_mcp_invalid_input', __('Invalid input. Use the types and choices returned by discovery.', 'press-permit-core'));
    }
}
