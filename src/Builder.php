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
use PDOStatement;
use PDOException;
use InvalidArgumentException;

class Builder
{
    /**
     * 该正则速度上比分2次调用单引号和双引号的正则慢上27倍之多
     * 但下面的例子单独调用2次的话会出问题
     * where name = 'abc"def' and remark like "%defk"
     * 预期的替换结果为
     * where name = _________ and remark like _______
     * 如果优先执行单引号替换则达到预期结果，但如果优先执行双引号替换，则会出现下面的结果
     * where name = 'abc_______________________%defk"
     *
     * @var string Both single and double quote mark pattern.
     */
    protected const MIXED_QUOTES_PATTERN = '/"[^\\"]*(?:\\.[^\\"]*)*"|\'[^\\\\\']*(?:\\\\.[^\\\\\']*)*\'/s';

    /**
     * 单独替换2种引号速度上更快，而且可以优先使用 strpos($str, '"') 来判断是否需要替换
     *
     * @var string Double quote mark pattern.
     */
    protected const DOUBLE_QUOTES_PATTERN = '/"[^\\"]*(?:\\.[^\\"]*)*"/s';

    /** @var string Single quote mark pattern. */
    protected const SINGLE_QUOTES_PATTERN = '/\'[^\\\\\']*(?:\\\\.[^\\\\\']*)*\'/s';

    /** @var string SQL-markup pattern. */
    protected const MARKUP_PATTERN = '/{{([a-zA-Z_][a-zA-Z0-9_]*)(\:(?:[^{}]++|(?R))*)?}}|\?|:[a-zA-Z0-9_]+/s';

    /** @var string SQL-markup label pattern. */
    protected const LABEL_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    /** @var array SQL-markup labels array. */
    protected $labels = [];

    /** @var array */
    protected $config = [
        'allowBuildOverride' => true,
        'useNamedPlaceholder' => true,
        'detectBindValueType' => false,
    ];

    /**
     * @param  array $config
     *  - allowBuildOverride: whether allow override build, default `true`.
     *  - useNamedPlaceholder: use named placeholders in prepare SQL, default `true`,
     *    if driver nosupport named placeholders, used question mark placeholders.
     *    **Current version only support named placeholders.**
     *  - detectBindValueType: auto detect bind value data type for PDO parameter, default `false`.
     * @return self
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Is allow override build.
     *
     * @return bool
     */
    protected function allowBuildOverride(): bool
    {
        return (bool)$this->config['allowBuildOverride'];
    }

    /**
     * Is auto detect bind value data type for PDO parameter.
     *
     * @return bool
     */
    protected function detectBindValueType(): bool
    {
        return (bool)$this->config['detectBindValueType'];
    }

    /**
     * Build a SQL-markup.
     *
     * @param  string $label  SQL-markup label.
     * @param  mixed  $bind   Value bind, if null this SQL-markup replace to empty string.
     * @param  string $sql    Replace the SQL-markup built-in SQL.
     * @return \Consatan\SQLBuilder\Builder
     * @throws \InvalidArgumentException  Throws if invalid label or bind.
     */
    public function build(string $label, $bind = [], string $sql = ''): Builder
    {
        if ($this->allowBuildOverride() || !isset($this->labels[$label])) {
            $this->assertLabel($label);

            $bind = $this->formatBind($bind);
            $this->labels[$label] = null === $bind ? ['', null] : [$sql, $bind];
        }

        return $this;
    }

    /**
     * Format bind values.
     *
     * @param  mixed $bind
     * @return ?array
     * @throws \InvalidArgumentException
     */
    protected function formatBind($bind): ?array
    {
        if (null === $bind) {
            return null;
        } elseif ($bind instanceof Bind) {
            return $bind->getValues();
        } elseif (is_callable($bind)) {
            return $this->formatBind($bind());
        } elseif (!is_array($bind)) {
            Bind::assertBindValue($bind);
            return [[$bind, $this->getPDOParamType($bind)]];
        }

        if (0 === ($len = count($bind))) {
            return [];
        }

        if (!is_map($bind) && !is_list($bind)) {
            throw new InvalidArgumentException('Mixed array is not allow.');
        }

        $values = [];
        foreach ($bind as $key => $val) {
            if ($val instanceof Bind) {
                $val = $val->getValues();
                if (empty($val)) {
                    $values[$key] = [];
                    continue;
                }

                if (!isset($val[0])) {
                    // bind array 里的 Bind 返回的应该都是索引型数组
                    // 仅在 bind array 长度为1且为索引型数组的情况下允许特殊兼容处理
                    // 这里是为了兼容这种常见习惯错误
                    // [Bind::int([':limit' => 5, ':offset' => 30)]]
                    if (1 === $len && 0 === $key) {
                        return $val;
                    }

                    throw new InvalidArgumentException('\Consatan\SQLBuilder\Bind in bind values array '
                        . 'must be an indexed array, associative array given.');
                }

                if (is_int($key)) {
                    $values = array_merge($values, $val);
                } else {
                    if (1 < count($val)) {
                        // [[['a', PDO::PARAM_STR], ['b', PDO::PARAM_STR]] 不存在这种情况
                        // [[['a', 'b'], PDO::PARAM_STR]] // where in 情况
                        throw new InvalidArgumentException('Bind value cannot be a multidimensional arrays.');
                    }

                    $values[$key] = $val[0];
                }
            } elseif (is_array($val)) {
                if (empty($val)) {
                    throw new InvalidArgumentException('Where in bind value cannot empty.');
                }

                // 这里使用 array_values 兼容各种 array，虽然理论上应该只允许索引型数组
                Bind::assertArrayBindValue($val = array_values($val));
                $values[$key] = [$val, $this->getPDOParamType($val[0])];
            } else {
                Bind::assertBindValue($val);
                $values[$key] = [$val, $this->getPDOParamType($val)];
            }
        }

        return $values;
    }

    /**
     * Detect bind value data type for PDO parameter.
     *
     * @param  mixed $value
     * @return int
     */
    protected function getPDOParamType($value): int
    {
        if ($this->detectBindValueType()) {
            if (is_int($value)) {
                return PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                return PDO::PARAM_BOOL;
            } elseif (is_null($value)) {
                return PDO::PARAM_NULL;
            }
        }

        return PDO::PARAM_STR;
    }

    /**
     * Prepare SQL
     *
     * @param  string $sql
     * @param  mixed  $bind
     * @param  array  $driverOptions PDO::preapre $driver_options parameter.
     * @return array  [prepare_sql, bind_value_array]
     * @throws \InvalidArgumentException
     */
    public function prepare(string $sql, $bind = [], array $driverOptions = []): array
    {
        $bind = $this->formatBind($bind);
        if (null === $bind) {
            $bind = ['', []];
        }
        return $this->compile($sql, $bind, $driverOptions);
    }

    /**
     * Compile SQL-markup.
     *
     * @param  string $sql
     * @param  array  $bind
     * @param  array  $driverOptions
     * @param  int    &$index
     * @param  array  &$bindValues
     * @param  array  &$whereInNamedPlaceholder
     * @return array  [prepare_sql, bind_value_array]
     * @throws \InvalidArgumentException
     */
    protected function compile(
        string $sql,
        array $bind = [],
        array $driverOptions = [],
        int &$index = 1,
        array &$bindValues = [],
        array &$whereInNamedPlaceholder = []
    ): array {
        $originSQL = $sql;
        // 先将所有引号内的内容（含引号）替换为空格，简单化后续的正则匹配
        $double = false !== strpos($sql, '"');
        $single = false !== strpos($sql, "'");
        $toSpace = function (array $match): string {
            return str_repeat(' ', strlen($match[0]));
        };

        if ($double && $single) {
            $sql = preg_replace_callback(self::MIXED_QUOTES_PATTERN, $toSpace, $sql);
        } elseif ($double) {
            $sql = preg_replace_callback(self::DOUBLE_QUOTES_PATTERN, $toSpace, $sql);
        } elseif ($single) {
            $sql = preg_replace_callback(self::SINGLE_QUOTES_PATTERN, $toSpace, $sql);
        }

        preg_match_all(self::MARKUP_PATTERN, $sql, $matches, PREG_OFFSET_CAPTURE);

        $questionMark = count(array_filter($matches[0], function ($val): bool {
            return $val[0] === '?';
        }));

        $namedPlaceholder = count(array_filter($matches[0], function ($val): bool {
            return $val[0][0] === ':';
        }));

        if ($questionMark !== 0 && $namedPlaceholder !== 0) {
            throw new InvalidArgumentException('Cannot use both question mark and named placeholders in an SQL.');
        }

        if ($questionMark !== 0 && count($bind) !== $questionMark) {
            throw new InvalidArgumentException(sprintf(
                'Placeholders quantity (%d) not match the Bind values quantity (%d).',
                $questionMark,
                count($bind)
            ));
        }

        $offset = 0;
        for ($i = 0; $i < count($matches[0]); $i++) {
            list($full, $pos) = $matches[0][$i];
            $pos += $offset;
            $fullen = strlen($full);

            if ('?' === $full) {
                list($value, $paramType) = array_shift($bind);
                if (is_array($value)) {
                    $j = 1;
                    $placeholder = [];
                    foreach ($value as $v) {
                        $placeholder[] = $key = ":__{$index}_{$j}__";
                        $bindValues[$key] = [$key, $v, $paramType];
                        $j++;
                    }

                    $placeholder = implode(',', $placeholder);
                } else {
                    $placeholder = ":__{$index}__";
                    $bindValues[$placeholder] = [$placeholder, $value, $paramType];
                }

                $index++;
                $originSQL = substr($originSQL, 0, $pos) . $placeholder . substr($originSQL, $pos + 1);
                $offset += strlen($placeholder) - 1;
            } elseif (':' === $full[0]) {
                if (isset($bindValues[$full])) {
                    continue;
                }

                if (!isset($bind[$full]) && !isset($bindValues["{$full}_1__"])) {
                    throw new InvalidArgumentException("No bind value on placeholder: \"{$full}\".");
                }

                list($value, $paramType) = $bind[$full];
                if (is_array($value)) {
                    if (array_key_exists($full, $whereInNamedPlaceholder)) {
                        $placeholder = $whereInNamedPlaceholder[$full];
                    } else {
                        $j = 1;
                        $placeholder = [];
                        foreach ($value as $v) {
                            $placeholder[] = $key = "{$full}_{$j}__";
                            $bindValues[$key] = [$key, $v, $paramType];
                            $j++;
                        }

                        $placeholder = $whereInNamedPlaceholder[$full] = implode(',', $placeholder);
                    }

                    $originSQL = substr($originSQL, 0, $pos) . $placeholder . substr($originSQL, $pos + $fullen);
                    $offset += strlen($placeholder) - $fullen;
                } else {
                    $bindValues[$full] = [$full, $value, $paramType];
                }
            } else {
                $label = $matches[1][$i][0];
                $builtInSQL = $matches[2][$i][0];
                if ('' !== $builtInSQL) {
                    $builtInSQL = substr($originSQL, $matches[2][$i][1] + $offset + 1, strlen($builtInSQL) - 1);
                }

                if (!isset($this->labels[$label])) {
                    $this->labels[$label] = ['', null];
                }

                list($labelSQL, $labelBind) = $this->labels[$label];
                if ('' === trim($labelSQL)) {
                    if (null === $labelBind) {
                        $originSQL = substr($originSQL, 0, $pos) . substr($originSQL, $pos + $fullen);
                        $offset -= $fullen;
                        continue;
                    }

                    $labelSQL = $builtInSQL;
                }

                list($subSQL, $_) = $this->compile(
                    $labelSQL,
                    $labelBind,
                    $driverOptions,
                    $index,
                    $bindValues,
                    $whereInNamedPlaceholder
                );

                $originSQL = substr($originSQL, 0, $pos) . $subSQL . substr($originSQL, $pos + $fullen);
                $offset += strlen($subSQL) - $fullen;
            }
        }

        return [$originSQL, $bindValues];
    }

    /**
     * Run the SQL query.
     *
     * @param  \PDO   $dbh
     * @param  string $sql
     * @param  mixed  $bind
     * @param  array  $driverOptions  PDO::prepare $driver_options parameter.
     * @return \PDOStatement
     * @throws \InvalidArgumentException
     */
    public function run(PDO $dbh, string $sql, $bind = [], array $driverOptions = []): PDOStatement
    {
        list($sql, $bind) = $this->prepare($sql, $bind, $driverOptions);
        $stmt = $dbh->prepare($sql, $driverOptions);
        if (false === $stmt) {
            $this->throwPDOException($dbh);
        }

        foreach ($bind as $val) {
            $stmt->bindValue(...$val);
        }

        if (false === $stmt->execute()) {
            $this->throwPDOException($stmt);
        }

        return $stmt;
    }

    /**
     * Throw a PDOException.
     *
     * @param  \PDO|\PDOStatement $db
     * @return void
     * @throws \PDOException
     */
    protected function throwPDOException($db): void
    {
        $error = $db->errorInfo();
        throw new PDOException(sprintf(
            'SQLSTATE[%s]: General error: %s %s',
            $error[0],
            $error[1],
            $error[2]
        ));
    }

    /**
     * Assert SQL-markup label.
     *
     * @param  string $label
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function assertLabel(string $label): void
    {
        if (1 !== preg_match(self::LABEL_PATTERN, $label)) {
            throw new InvalidArgumentException("Invalid SQL-markup label: \"{$label}\".");
        }
    }
}
