<?php

namespace PressShack;

\defined('ABSPATH') || exit;

class LibArray
{
    // derived from http://us3.php.net/manual/en/ref.array.php#80631
    public static function flatten($arr_md, $go_deep = false)
    { //flattens multi-dim arrays (if go_deep, supports > 2D but destroys keys)
        if (!is_array($arr_md)) return [];

        $arr_flat = [];

        foreach ($arr_md as $element) {
            if (is_array($element)) {
                if ($go_deep)
                    $arr_flat = array_merge($arr_flat, self::flatten($element, true));
                else
                    $arr_flat = array_merge($arr_flat, $element);
            } else
                array_push($arr_flat, $element);
        }

        return $arr_flat;
    }

    public static function setElem(&$arr, $dims)
    {
        $elem = &$arr;

        foreach ($dims as $dim => $val) {
            if (!isset($elem[$val]))
                $elem[$val] = [];

            $elem = &$elem[$val];
        }
    }

    public static function implode($delim, $arr, $wrap_open = ' ( ', $wrap_close = ' ) ')
    {
        if (!is_array($arr))
            return $arr;

        $delim = "$wrap_close $delim $wrap_open";

        if (count($arr)) {
            $arr = array_unique($arr);
            return $wrap_open . implode($delim, $arr) . $wrap_close;
        } else {
            return reset($arr);
        }
    }

    public static function getPropertyArray(&$arr, $id_prop, $buffer_prop)
    {
        if (!is_array($arr))
            return;

        $buffer = [];

        foreach (array_keys($arr) as $key)
            $buffer[$arr[$key]->$id_prop] = (isset($arr[$key]->$buffer_prop)) ? $arr[$key]->$buffer_prop : '';

        return $buffer;
    }

    public static function restorePropertyArray(&$target_arr, $buffer_arr, $id_prop, $buffer_prop)
    {
        if (!is_array($target_arr) || !is_array($buffer_arr))
            return;

        foreach (array_keys($target_arr) as $key)
            if (isset($buffer_arr[$target_arr[$key]->$id_prop]))
                $target_arr[$key]->$buffer_prop = $buffer_arr[$target_arr[$key]->$id_prop];
    }

    public static function subset($arr, $keys)
    {
        return array_intersect_key($arr, array_fill_keys($keys, true));
    }
}
