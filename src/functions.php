<?php

/**
 * This file is part of SQLBuilder.
 *
 * @author Chopin Ngo <consatan@gmail.com>
 * @license https://opensource.org/licenses/bsd-3-clause BSD-3-Clause
 */

declare(strict_types=1);

namespace Consatan\SQLBuilder;

/**
 * Is an associative array?
 *
 * @param  array $arr
 * @return bool
 */
function is_map(array $arr): bool
{
    $keys = array_keys($arr);
    return $keys === array_filter($keys, 'is_string');
}

/**
 * Is an Indexed array?
 *
 * @param  array $arr
 * @return bool
 */
function is_list(array $arr): bool
{
    $keys = array_keys($arr);
    return $keys === array_keys($keys);
}
