# SQL Builder

[![License](https://img.shields.io/github/license/consatan/sqlbuilder)](LICENSE)
[![Php Version](https://img.shields.io/badge/php-%3E=7.1-brightgreen.svg?maxAge=2592000)](https://packagist.org/packages/consatan/sqlbuilder)
[![Build Status](https://travis-ci.org/consatan/sqlbuilder.svg?branch=master)](https://travis-ci.org/consatan/sqlbuilder)
[![Coverage Status](https://coveralls.io/repos/github/consatan/sqlbuilder/badge.svg?branch=master)](https://coveralls.io/github/consatan/sqlbuilder?branch=master)

让复杂 SQL 不再复杂


## 安装

```bash
composer require consatan/sqlbuilder
```

## 起因

虽然数据库操作类都支持多表关联查询，也支持纯 SQL 查询，但你是否遇到过复杂查询，又有多个判断条件的情况？

```php
```

这个工具要解决的就是这类问题，如下

```php
<?php
use Consatan\SQLBuilder\Bind;
use Consatan\SQLBuilder\Builder;

$sql = <<<SQL
SQL;

$builder = new Builder();

try {
    $stmt = $builder
        ->build('')
        ->build('')
        ->build('')
        ->build('')
        ->run($dbh, $sql);

    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
        // do something...
    }
} catch (\InvalidArgumentException $e) {
    // 参数错误才会抛出这个异常，理论上这个异常应该在测试阶段发现
} catch (\PDOException $e) {
    // PDO 查询异常，必须捕获
}
```

## SQL-markup 规则

## TODO
- [ ] useNamedPlaceholder = false 支持
- [ ] 根据 `driver_options` 判断是否支持 named placeholders

## 协议

[BSD-3-Clause](LICENSE)

## API 文档

[TOC]

### Builder 类

Builder SQL 语句。

#### 类概要

```php
Consatan\SQLBuilder\Builder {
    public __construct(array $config = [])]
    public build(string $label, mixed $bind = [], string $sql = ''): Builder
    public prepare(string $sql, mixed $bind = [], array $driverOptions = []): array
    public run(\PDO $dbh, string $sql, mixed $bind = [], array $driverOptions = []): \PDOStatement
}
```

#### Builder::__construct

实例化 Builder 类

```php
public Builder::__construct(array $config = [])
```

##### 参数说明

`$config` 支持以下实例化选项
- `allowBuildOverride`: 是否允许覆盖 build，默认 `true`。
- `useNamedPlaceholder`: 在 prepare SQL 时使用命名占位符，默认 `true`。某些 PDO 驱动只支持问号形式的占位符，这种情况下需要手动配置该参数。**该参数当前未实现**。
- `detectBindValueType`: 根据 PHP 变量数据类型检测对应的 SQL 绑定参数类型，默认 `false`。

##### 返回值

成功返回 Consatan\SQLBuilder\Builder 实例。

##### 使用示例


#### Builder::build

build 一个 SQL-markup。

```php
public Builder::build(string $label, mixed $bind = [], string $sql = ''): Builder
```

##### 参数说明
`$label` SQL-markup 标签，允许的标签格式为 ``

`$bind` 绑定参数列表

`$sql` 传递该参数将覆盖 SQL-markup 中的内置 SQL 语句

##### 返回值

返回当前 Consatan\SQLBuilder\Builder 实例，便于链式调用。

##### 错误/异常

当参数格式错误时抛出 \InvalidArgumentException 异常。

##### 使用示例


#### Builder::prepare

对 builder 的 SQL 进行 prepare 化操作。

```php
public Builder::prepare(string $sql, mixed $bind = [], array $driverOptions = []): array
```

##### 参数说明

`$sql` 要执行的 SQL 语句。

`$bind` 绑定参数列表。

`$driverOptions` 参见 [`PDO::prepare`](https://www.php.net/manual/en/pdo.prepare.php#refsect1-pdo.prepare-parameters) 的 `$driver_options` 参数说明。

##### 返回值

返回格式如下的数组

##### 错误/异常

当参数错误时抛出 \InvalidArgumentException 异常。

##### 使用示例


#### Builder::run

执行 builder 的 SQL。

```php
public Builder::run(\PDO $dbh, string $sql, mixed $bind = [], array $driverOptions = []): \PDOStatement
```

##### 参数说明

`$dbh` PDO 实例。

`$sql` 见 Builder::prepare 的 $sql 参数说明。

`$bind` 见 Builder::prepare 的 $bind 参数说明。

`$driverOptions` 见 Builder::prepare 的 $driverOptions 参数说明。

##### 返回值

成功返回 \PDOStatement 实例。

##### 错误/异常

当参数错误时抛出 \InvalidArgumentException 异常。

##### 使用示例



### Bind 类

PDO 绑定参数辅助类。

#### 类概要

```php
Consatan\SQLBuilder\Bind {
    public getValues(): array
    final public static int(mixed ...$bind): Bind
    final public static str(mixed ...$bind): Bind
    final public static null(mixed $val = null, mixed ...$bind): Bind
    final public static bool(mixed ...$bind): Bind
    final public static lob(mixed ...$bind): Bind
    final public static assertBindValue(mixed $value): void
    final public static assertArrayBindValue(array $array): void
    final public static assertNamedPlaceholder(string $placeholder): void
    final public static assertPDOParamType(int $type): void
}
```

#### Bind::getValues

获取格式化后的 PDO 参数绑定数据

```php
public Bind::getValues(): array
```

##### 返回值

格式化后的 PDO 参数绑定数据，格式如下

##### 使用示例

#### Bind::int

对给定数据进行 PDO int 数据类型绑定。

```php
final public static Bind::int(mixed ...$bind): Bind
```

##### 参数说明

`...$bind`

##### 返回值

成功返回 Consatan\SQLBuilder\Bind 实例。

##### 错误\异常

绑定参数值无效抛出 \InvalidArgumentException 异常。

##### 使用示例


#### Bind::str

对给定数据进行 PDO str 数据类型绑定。

```php
final public static Bind::str(mixed ...$bind): Bind
```

见 Bind::int 说明。

#### Bind::null

对给定数据进行 PDO null 数据类型绑定。

```php
final public static Bind::null(mixed $val = null, mixed ...$bind): Bind
```

##### 参数说明

`$val`

其余见 Bind::int 说明。

#### Bind::bool

对给定数据进行 PDO bool 数据类型绑定。

```php
final public static Bind::bool(mixed ...$bind): Bind
```

见 Bind::int 说明。

#### Bind::lob

对给定数据进行 PDO lob 数据类型绑定。

```php
final public static Bind::lob(mixed ...$bind): Bind
```

见 Bind::int 说明。

#### Bind::assertBindValue

断言绑定参数是否合法。

```php
final public static Bind::assertBindValue(mixed $bind): void
```

##### 参数说明

`$bind` 要断言的绑定参数，必须是 scalar 或 null 数据类型。

##### 错误/异常

绑定参数不合法抛出 \InvalidArgumentException 异常。

##### 使用示例


#### Bind::assertArrayBindValue

Array 版的 Bind::assertBindValue。

```php
final public static Bind::assertArrayBindValue(array $array): void
```

##### 参数说明

`$array` 要断言的绑定参数数组，数组元素必须是 scalar 或 null 数据类型。

##### 错误/异常

绑定参数不合法抛出 \InvalidArgumentException 异常。

##### 使用示例

#### Bind::assertNamedPlaceholder

断言命名占位符格式。

```php
final public static Bind::assertNamedPlaceholder(string $placeholder): void
```

##### 参数说明

`$placeholder` 要断言的命名占位符变量。命名占位符必须符合以下格式。

##### 错误/异常

命名占位符不合法抛出 \InvalidArgumentException 异常。

##### 使用示例

#### Bind::assertPDOParamType

断言 PDO 参数绑定数据类型。

```php
final public static Bind::assertPDOParamType(int $type): void
```

##### 参数说明

`$type` 要断言的 PDO 参数绑定数据类型，详见

##### 错误/异常

$type 为未定义的 PDO 参数绑定数据类型时抛出 \InvalidArgumentException 异常。

##### 使用示例
