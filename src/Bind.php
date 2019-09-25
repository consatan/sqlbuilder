<?php

/**
 * This file is part of SQLBuilder.
 *
 * @author Chopin Ngo <consatan@gmail.com>
 * @license https://opensource.org/licenses/bsd-3-clause BSD-3-Clause
 */

declare(strict_types=1);

namespace Consatan\SQLBuilder;

use PDO;
use InvalidArgumentException;

class Bind
{
    /** @var string */
    const NAMED_PLACEHOLDER_PATTERN = '/^:[a-zA-Z0-9_]+$/';

    /** @var array Bind values. */
    protected $values = [];

    /**
     * @param array $values
     * @param int   $type
     */
    protected function __construct(array $values, int $type)
    {
        self::assertPDOParamType($type);

        $isAssocArray = false;
        foreach ($values as $value) {
            if (is_array($value)) {
                if (!($isMap = is_map($value)) && !is_list($value)) {
                    throw new InvalidArgumentException('Mixed array is not allow.');
                }

                if ($isMap) {
                    if (!$isAssocArray && !empty($this->values)) {
                        throw new InvalidArgumentException(
                            'Mixed array is not allow, string key not allowed push to indexed array.'
                        );
                    }

                    $isAssocArray = true;
                    foreach ($value as $key => $val) {
                        self::assertNamedPlaceholder($key);
                        if (is_array($val)) {
                            // where in
                            self::assertArrayBindValue($val = array_values($val));
                        } else {
                            self::assertBindValue($val);
                        }

                        $this->values[$key] = [$val, $type];
                    }
                } else {
                    $this->throwIfIsAssocArray($isAssocArray);

                    self::assertArrayBindValue($value = array_values($value));
                    $this->values[] = [$value, $type];
                }
            } else {
                $this->throwIfIsAssocArray($isAssocArray);

                self::assertBindValue($value);
                $this->values[] = [$value, $type];
            }
        }
    }

    /**
     * Get bind values.
     *
     * @return array
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * If is an associative array throw InvalidArgumentException.
     *
     * @param bool $isAssoc
     * @throws \InvalidArgumentException If true throw this exception.
     */
    private function throwIfIsAssocArray(bool $isAssoc): void
    {
        if ($isAssoc) {
            throw new InvalidArgumentException(
                'Mixed array is not allow, numeric key not allowed push to associative array.'
            );
        }
    }

    /**
     * Bind int values.
     *
     * @param  mixed ...$bind Bind values.
     * @return Bind
     * @throws \InvalidArgumentException If values invalid.
     */
    final public static function int(...$bind): Bind
    {
        return new self($bind, PDO::PARAM_INT);
    }

    /**
     * Bind string values.
     *
     * @param  mixed ...$bind Bind values.
     * @return Bind
     * @throws \InvalidArgumentException If values invalid.
     */
    final public static function str(...$bind): Bind
    {
        return new self($bind, PDO::PARAM_STR);
    }

    /**
     * Bind null values.
     *
     * @param  mixed $val
     * @param  mixed ...$bind Bind values.
     * @return Bind
     * @throws \InvalidArgumentException If values invalid.
     */
    final public static function null($val = null, ...$bind): Bind
    {
        array_unshift($bind, $val);
        return new self($bind, PDO::PARAM_NULL);
    }

    /**
     * Bind boolean values.
     *
     * @param  mixed ...$bind Bind values.
     * @return Bind
     * @throws \InvalidArgumentException If values invalid.
     */
    final public static function bool(...$bind): Bind
    {
        return new self($bind, PDO::PARAM_BOOL);
    }

    /**
     * Bind SQL large object data values.
     *
     * @param  mixed ...$bind Bind values.
     * @return Bind
     * @throws \InvalidArgumentException If values invalid.
     */
    final public static function lob(...$bind): Bind
    {
        return new self($bind, PDO::PARAM_LOB);
    }

    /**
     * Assert bind value is a scalar or null.
     *
     * @param  mixed $value
     * @throws \InvalidArgumentException If values invalid.
     */
    final public static function assertBindValue($value): void
    {
        if (!is_scalar($value) && null !== $value) {
            throw new InvalidArgumentException(sprintf(
                'Bind value must be a scalar or null, %s given.',
                gettype($value)
            ));
        }
    }

    /**
     * Assert array values is valid bind value.
     *
     * @param  array $array
     * @throws \InvalidArgumentException If values invalid.
     */
    final public static function assertArrayBindValue(array $array): void
    {
        foreach ($array as $val) {
            self::assertBindValue($val);
        }
    }

    /**
     * Assert named placeholder is valid.
     *
     * @param  string $placeholder
     * @throws \InvalidArgumentException If placeholder invalid.
     */
    final public static function assertNamedPlaceholder(string $placeholder): void
    {
        if (1 !== preg_match(self::NAMED_PLACEHOLDER_PATTERN, $placeholder)) {
            throw new InvalidArgumentException("Invalid SQL named placeholder, \"{$placeholder}\".");
        }
    }

    /**
     * Assert type is an invalid data type for PDO parameter.
     *
     * @param  int $type
     * @throws \InvalidArgumentException If invalid data type.
     */
    final public static function assertPDOParamType(int $type): void
    {
        if (PDO::PARAM_STR !== $type
            && PDO::PARAM_INT !== $type
            && PDO::PARAM_NULL !== $type
            && PDO::PARAM_BOOL !== $type
            && PDO::PARAM_LOB !== $type
        ) {
            throw new InvalidArgumentException("Invalid data type for PDO parameter, \"{$type}\".");
        }
    }
}
