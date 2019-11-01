<?php

/**
 * This file is part of SQLBuilder.
 *
 * @author Chopin Ngo <consatan@gmail.com>
 * @license https://opensource.org/licenses/bsd-3-clause BSD-3-Clause
 */

declare(strict_types=1);

namespace Consatan\SQLBuilder;

use InvalidArgumentException;

interface BindInterface
{
    /**
     * Bind int values.
     *
     * @param  mixed ...$bind Bind values.
     * @return BindInterface
     * @throws \InvalidArgumentException If values invalid.
     */
    public static function int(...$bind): BindInterface;

    /**
     * Bind string values.
     *
     * @param  mixed ...$bind Bind values.
     * @return BindInterface
     * @throws \InvalidArgumentException If values invalid.
     */
    public static function str(...$bind): BindInterface;

    /**
     * Bind null values.
     *
     * @param  mixed $val
     * @param  mixed ...$bind Bind values.
     * @return BindInterface
     * @throws \InvalidArgumentException If values invalid.
     */
    public static function null($val = null, ...$bind): BindInterface;

    /**
     * Bind boolean values.
     *
     * @param  mixed ...$bind Bind values.
     * @return BindInterface
     * @throws \InvalidArgumentException If values invalid.
     */
    public static function bool(...$bind): BindInterface;

    /**
     * Bind SQL large object data values.
     *
     * @param  mixed ...$bind Bind values.
     * @return BindInterface
     * @throws \InvalidArgumentException If values invalid.
     */
    public static function lob(...$bind): BindInterface;
}
