<?php

/**
 * This file is part of SQLBuilder.
 *
 * @author Chopin Ngo <consatan@gmail.com>
 * @license https://opensource.org/licenses/bsd-3-clause BSD-3-Clause
 */

declare(strict_types=1);

namespace Consatan\SQLBuilder\Test;

use PDO;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Consatan\SQLBuilder\Bind;

class BindTest extends TestCase
{
    public function testPDOParamType()
    {
        $int = Bind::int(1);
        $this->assertTrue($int instanceof Bind);
        $this->assertSame($int->getValues(), [[1, PDO::PARAM_INT]]);

        $str = Bind::str('str');
        $this->assertSame($str->getValues(), [['str', PDO::PARAM_STR]]);

        $null = Bind::null(null);
        $this->assertSame($null->getValues(), [[null, PDO::PARAM_NULL]]);

        $null = Bind::null('not null');
        $this->assertSame($null->getValues(), [['not null', PDO::PARAM_NULL]]);

        $bool = Bind::bool(false);
        $this->assertSame($bool->getValues(), [[false, PDO::PARAM_BOOL]]);

        $lob = Bind::lob('lob value');
        $this->assertSame($lob->getValues(), [['lob value', PDO::PARAM_LOB]]);
    }

    public function testArray()
    {
        $bind = Bind::int();
        $this->assertSame($bind->getValues(), []);

        $bind = Bind::int([1, 2, 3]);
        $this->assertSame($bind->getValues(), [[[1, 2, 3], PDO::PARAM_INT]]);

        $bind = Bind::int(1, 2, [3, 4], 5);
        $this->assertSame($bind->getValues(), [
            [1, PDO::PARAM_INT],
            [2, PDO::PARAM_INT],
            [[3, 4], PDO::PARAM_INT],
            [5, PDO::PARAM_INT],
        ]);

        $bind = Bind::str([':name' => 'chopin', ':addr' => 'Amoy']);
        $this->assertSame($bind->getValues(), [
            ':name' => ['chopin', PDO::PARAM_STR],
            ':addr' => ['Amoy', PDO::PARAM_STR],
        ]);

        $bind = Bind::str([':name' => 'chopin'], [':addr' => 'Amoy']);
        $this->assertSame($bind->getValues(), [
            ':name' => ['chopin', PDO::PARAM_STR],
            ':addr' => ['Amoy', PDO::PARAM_STR],
        ]);

        $bind = Bind::str([':name' => 'chopin', ':age' => [1, 2, 3]]);
        $this->assertSame($bind->getValues(), [
            ':name' => ['chopin', PDO::PARAM_STR],
            ':age' => [[1, 2, 3], PDO::PARAM_STR],
        ]);

        $bind = Bind::str([':name' => 'chopin', ':age' => ['baby' => 1, 'children' => 18, 'adult' => 60]]);
        $this->assertSame($bind->getValues(), [
            ':name' => ['chopin', PDO::PARAM_STR],
            ':age' => [[1, 18, 60], PDO::PARAM_STR],
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Mixed array is not allow.
     */
    public function testMixedArray()
    {
        $bind = Bind::int([':limit' => 10, ':offset' => 20, 2, 3]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Mixed array is not allow, numeric key not allowed push to associative array.
     */
    public function testNumericKeyPushToAssocArray()
    {
        $bind = Bind::int([':limit' => 10, ':offset' => 20], 1, 2, 3);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Mixed array is not allow, string key not allowed push to indexed array.
     */
    public function testStringKeyPushToIndexedArray()
    {
        $bind = Bind::int(1, 2, 3, [':limit' => 10]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Bind value must be a scalar or null, object given.
     */
    public function testBindInvalidValue()
    {
        $bind = Bind::int(1, new \StdClass());
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid SQL named placeholder, "limit".
     */
    public function testInalidPlaceholder()
    {
        $bind = Bind::int(['limit' => 10, 'offset' => 20]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid data type for PDO parameter, "100".
     */
    public function testInvalidPDOParamType()
    {
        Bind::assertPDOParamType(100);
    }
}
