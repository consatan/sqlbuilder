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
use PDOException;
use PDOStatement;
use ReflectionMethod;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Consatan\SQLBuilder\Bind;
use Consatan\SQLBuilder\Builder;

class BuilderTest extends TestCase
{
    public function testFormatBind()
    {
        $builder = new Builder();
        $formatBind = new ReflectionMethod(Builder::class, 'formatBind');
        $formatBind->setAccessible(true);

        $bind = $formatBind->invoke($builder, null);
        $this->assertNull($bind);

        $bind = $formatBind->invoke($builder, function () {
            return null;
        });
        $this->assertNull($bind);

        $bind = $formatBind->invoke($builder, 1);
        $this->assertSame($bind, [[1, PDO::PARAM_STR]]);

        $bind = $formatBind->invoke($builder, []);
        $this->assertSame($bind, []);

        $bind = $formatBind->invoke($builder, Bind::int(1, 2, 3));
        $this->assertSame($bind, [
            [1, PDO::PARAM_INT],
            [2, PDO::PARAM_INT],
            [3, PDO::PARAM_INT],
        ]);

        $bind = $formatBind->invoke($builder, ['chopin', Bind::int(33), 'foo', Bind::null(null)]);
        $this->assertSame($bind, [
            ['chopin', PDO::PARAM_STR],
            [33, PDO::PARAM_INT],
            ['foo', PDO::PARAM_STR],
            [null, PDO::PARAM_NULL],
        ]);

        $bind = $formatBind->invoke($builder, Bind::int());
        $this->assertSame($bind, []);

        $bind = $formatBind->invoke($builder, [Bind::int()]);
        $this->assertSame($bind, [[]]);

        $bind = $formatBind->invoke($builder, [Bind::int([':limit' => 10, ':offset' => 20])]);
        $this->assertSame($bind, [
            ':limit' => [10, PDO::PARAM_INT],
            ':offset' => [20, PDO::PARAM_INT],
        ]);

        $bind = $formatBind->invoke($builder, ['foo', 'bar', [1, 2, 3]]);
        $this->assertSame($bind, [
            ['foo', PDO::PARAM_STR],
            ['bar', PDO::PARAM_STR],
            [[1, 2, 3], PDO::PARAM_STR],
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Where in bind value cannot empty.
     */
    public function testWhereInEmpty()
    {
        $formatBind = new ReflectionMethod(Builder::class, 'formatBind');
        $formatBind->setAccessible(true);

        $formatBind->invoke(new Builder(), ['foo', 'bar', []]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Mixed array is not allow.
     */
    public function testMixedArray()
    {
        $formatBind = new ReflectionMethod(Builder::class, 'formatBind');
        $formatBind->setAccessible(true);

        $formatBind->invoke(new Builder(), ['foo', 'bar', ':limit' => 10]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Bind value cannot be a multidimensional arrays.
     */
    public function testWhereInMultiArray()
    {
        $formatBind = new ReflectionMethod(Builder::class, 'formatBind');
        $formatBind->setAccessible(true);

        $formatBind->invoke(new Builder(), [':foo' => Bind::int(1, 2)]);
    }

    public function testMultipAssocBind()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('\Consatan\SQLBuilder\Bind in bind values array '
            . 'must be an indexed array, associative array given.');
        $formatBind = new ReflectionMethod(Builder::class, 'formatBind');
        $formatBind->setAccessible(true);

        $formatBind->invoke(new Builder(), [Bind::int([':offset' => 20]), Bind::int([':limit' => 10])]);
    }

    public function testPrepare()
    {
        $builder = new Builder();
        $sql = 'select * from user where name=:name and state=:state order by id {{limit: limit ?,?}}';

        $builder->build('limit', Bind::int(10, 20));
        $result = $builder->prepare($sql, [':name' => 'chopin', ':state' => 1]);

        $this->assertSame($result, [
            'select * from user where name=:name and state=:state order by id  limit :__1__,:__2__',
            [
                ':name' => [':name', 'chopin', PDO::PARAM_STR],
                ':state' => [':state', 1, PDO::PARAM_STR],
                ':__1__' => [':__1__', 10, PDO::PARAM_INT],
                ':__2__' => [':__2__', 20, PDO::PARAM_INT],
            ]
        ]);

        $builder = new Builder();
        $builder->build('limit', null);
        $result = $builder->prepare($sql, [':name' => 'chopin', ':state' => 1]);

        $this->assertSame($result, [
            'select * from user where name=:name and state=:state order by id ',
            [
                ':name' => [':name', 'chopin', PDO::PARAM_STR],
                ':state' => [':state', 1, PDO::PARAM_STR],
            ]
        ]);

        $builder = new Builder();
        $sql = 'select * from user where name=:name and state=:state {{age_in: and age in (?)}} order by id';

        $builder->build('age_in', Bind::int([18, 24, 36]));
        $result = $builder->prepare($sql, [':name' => 'chopin', ':state' => 1]);
        $this->assertSame($result, [
            'select * from user where name=:name and state=:state  and age in (:__1_1__,:__1_2__,:__1_3__) order by id',
            [
                ':name' => [':name', 'chopin', PDO::PARAM_STR],
                ':state' => [':state', 1, PDO::PARAM_STR],
                ':__1_1__' => [':__1_1__', 18, PDO::PARAM_INT],
                ':__1_2__' => [':__1_2__', 24, PDO::PARAM_INT],
                ':__1_3__' => [':__1_3__', 36, PDO::PARAM_INT],
            ]
        ]);

        $builder = new Builder();
        $sql = 'select * from user where name=:name and state=:state {{age_in: and age in (:age)}} order by id';

        $builder->build('age_in', Bind::int([':age' => [18, 24, 36]]));
        $result = $builder->prepare($sql, [':name' => 'chopin', ':state' => 1]);
        $this->assertSame($result, [
            'select * from user where name=:name and state=:state  and age in (:age_1__,:age_2__,:age_3__) order by id',
            [
                ':name' => [':name', 'chopin', PDO::PARAM_STR],
                ':state' => [':state', 1, PDO::PARAM_STR],
                ':age_1__' => [':age_1__', 18, PDO::PARAM_INT],
                ':age_2__' => [':age_2__', 24, PDO::PARAM_INT],
                ':age_3__' => [':age_3__', 36, PDO::PARAM_INT],
            ]
        ]);

        $builder = new Builder();
        $sql = 'select * from user where state=? {{search:and (name like :keyword or mobile like :keyword)}} '
            . '{{age_in: and age in (:age)}}';

        $builder->build('search', [':keyword' => '%13800138%']);
        $result = $builder->prepare($sql, Bind::int(1));
        $this->assertSame($result, [
            'select * from user where state=:__1__ and (name like :keyword or mobile like :keyword) ',
            [
                ':__1__' => [':__1__', 1, PDO::PARAM_INT],
                ':keyword' => [':keyword', '%13800138%', PDO::PARAM_STR],
            ]
        ]);

        $builder = new Builder();
        $sql = 'select * from user where name="chopin" {{state: and state=?}}';

        $builder->build('state', 1);
        $result = $builder->prepare($sql);
        $this->assertSame($result, [
            'select * from user where name="chopin"  and state=:__1__',
            [':__1__' => [':__1__', 1, PDO::PARAM_STR]],
        ]);

        $builder = new Builder();
        $sql = 'select * from user as u, order as o where u.id=o.user_id and o.state in (:state)'
            . '{{user_state: and u.state in (:state)}} {{create_in: and o.created_at between ? and ?}}';
        $state = Bind::int([':state' => [1, 2]]);
        $builder->build('user_state', $state);
        $builder->build('create_in', Bind::int(123, 456));
        $result = $builder->prepare($sql, $state);
        $this->assertSame($result, [
            'select * from user as u, order as o where u.id=o.user_id and o.state in (:state_1__,:state_2__) '
                . 'and u.state in (:state_1__,:state_2__)  and o.created_at between :__1__ and :__2__',
            [
                ':state_1__' => [':state_1__', 1, PDO::PARAM_INT],
                ':state_2__' => [':state_2__', 2, PDO::PARAM_INT],
                ':__1__' => [':__1__', 123, PDO::PARAM_INT],
                ':__2__' => [':__2__', 456, PDO::PARAM_INT],
            ],
        ]);

        $builder = new Builder();
        $sql = 'select * from user where name="chopin" {{mobile:and mobile like \'%13800%\'{{age: and age > ?}}}}';
        $builder->build('mobile');
        $builder->build('age', 18);
        $result = $builder->prepare($sql);
        $this->assertSame($result, [
            'select * from user where name="chopin" and mobile like \'%13800%\' and age > :__1__',
            [':__1__' => [':__1__', 18, PDO::PARAM_STR]],
        ]);

        $builder = new Builder();
        $sql = 'select * from user where 1=1 {{name: and name=?}}';
        $builder->build('name', 'chopin');
        $result = $builder->prepare($sql, null);
        $this->assertSame($result, [
            'select * from user where 1=1  and name=:__1__',
            [':__1__' => [':__1__', 'chopin', PDO::PARAM_STR]],
        ]);

        $builder = new Builder();
        $sql = 'select * from user where name=:name {{age: and age in (?)}} order by id {{limit: limit ?,?}}';
        $builder->build('age', 32, ' and age =?');
        $builder->build('limit', Bind::int(10, 20));
        $result = $builder->prepare($sql, [':name' => 'chopin']);
        $this->assertSame($result, [
            'select * from user where name=:name  and age =:__1__ order by id  limit :__2__,:__3__',
            [
                ':name' => [':name', 'chopin', PDO::PARAM_STR],
                ':__1__' => [':__1__', 32, PDO::PARAM_STR],
                ':__2__' => [':__2__', 10, PDO::PARAM_INT],
                ':__3__' => [':__3__', 20, PDO::PARAM_INT],
            ]
        ]);
    }

    public function testDetectBindValueType()
    {
        $builder = new Builder(['detectBindValueType' => true]);
        $sql = 'select * from user where name=? {{age: and age in (?)}} {{remark: and remark=?}} {{bool: and bool=?}}';
        $builder->build('age', [[1, 2]]);
        $builder->build('remark', [null]);
        $builder->build('bool', false);
        $result = $builder->prepare($sql, 'chopin');
        $this->assertSame($result, [
            'select * from user where name=:__1__  and age in (:__2_1__,:__2_2__)  and remark=:__3__  and bool=:__4__',
            [
                ':__1__' => [':__1__', 'chopin', PDO::PARAM_STR],
                ':__2_1__' => [':__2_1__', 1, PDO::PARAM_INT],
                ':__2_2__' => [':__2_2__', 2, PDO::PARAM_INT],
                ':__3__' => [':__3__', null, PDO::PARAM_NULL],
                ':__4__' => [':__4__', false, PDO::PARAM_BOOL],
            ]
        ]);
    }

    public function testDisableOverrideBuild()
    {
        $builder = new Builder(['allowBuildOverride' => false]);
        $sql = 'select * from user where name=? {{age: and age=?}}';
        $builder->build('age', 1);
        $result = $builder->prepare($sql, 'chopin');

        $expect = [
            'select * from user where name=:__1__  and age=:__2__',
            [
                ':__1__' => [':__1__', 'chopin', PDO::PARAM_STR],
                ':__2__' => [':__2__', 1, PDO::PARAM_STR],
            ]
        ];

        $this->assertSame($result, $expect);

        $builder->build('age', 2);
        $result = $builder->prepare($sql, 'chopin');
        $this->assertSame($result, $expect);

        $builder = new Builder();
        $builder->build('age', 1);
        $result = $builder->prepare($sql, 'chopin');
        $this->assertSame($result, $expect);

        $expect[1][':__2__'][1] = 2;
        $builder->build('age', 2);
        $result = $builder->prepare($sql, 'chopin');
        $this->assertSame($result, $expect);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Cannot use both question mark and named placeholders in an SQL.
     */
    public function testUseTwoStylePlaceholder()
    {
        $builder = new Builder();
        $sql = 'select * from user where name=:name and state=?';
        $builder->prepare($sql, ['chopin', 1]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Placeholders quantity (2) not match the Bind values quantity (1).
     */
    public function testBindQtyNotMatchQuestionMarkPlaceholder()
    {
        $builder = new Builder();
        $sql = 'select * from user where name=? and state=?';
        $builder->prepare($sql, ['chopin']);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage No bind value on placeholder: ":state".
     */
    public function testNotBindValueOnPlaceholder()
    {
        $builder = new Builder();
        $sql = 'select * from user where name=:name and state=:state';
        $builder->prepare($sql, [':name' => 'chopin']);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid SQL-markup label: "0name".
     */
    public function testInvalidLabel()
    {
        $builder = new Builder();
        $builder->build('0name');
    }

    protected function setupDB()
    {
        $ddl = <<<SQL
create table user(
    id INTEGER PRIMARY KEY,
    mobile TEXT,
    name TEXT,
    age INTEGER,
    state INTEGER,
    created_at INTEGER
)
SQL;
        $data = <<<SQL
insert into user(mobile, name, age, state, created_at) values
('10086', 'CMCC', 20, 1, 1569343838),
('10000', 'CTCC', 21, 1, 1569343838),
('10010', 'CUCC', 22, 1, 1569343838)
SQL;

        $dbh = new PDO('sqlite::memory:');
        $dbh->query($ddl);
        $dbh->query($data);

        return $dbh;
    }

    public function testRun()
    {
        $dbh = $this->setupDB();
        $sql = 'select id,name from user where state=?{{age: and age>?}} order by id {{limit: limit :limit,:offset}}';
        $builder = new Builder();
        $builder->build('age', 20);
        $stmt = $builder->run($dbh, $sql, 1);
        $this->assertTrue($stmt instanceof PDOStatement);
        $this->assertSame($stmt->fetchAll(PDO::FETCH_ASSOC), [
            ['id' => '2', 'name' => 'CTCC'],
            ['id' => '3', 'name' => 'CUCC'],
        ]);
    }

    /**
     * @expectedException PDOException
     * @expectedExceptionMessage SQLSTATE[HY000]: General error: 1 no such column: not_exists_field
     */
    public function testRunPrepareFail()
    {
        $dbh = $this->setupDB();
        $sql = 'select not_exists_field from user where state=?{{age: and age>?}} order by id';
        $builder = new Builder();
        $builder->build('age', 20);
        $builder->run($dbh, $sql, 1);
    }

    /**
     * @expectedException PDOException
     * @expectedExceptionMessage SQLSTATE[42S22]: General error: 1054 Unknown column 'not_exists_field' in 'field list'
     */
    public function testRunExecuteFail()
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)->setMethods(['execute', 'errorInfo'])->getMock();
        $stmt->method('execute')->willReturn(false);
        $stmt->method('errorInfo')->willReturn([
            '42S22',
            1054,
            "Unknown column 'not_exists_field' in 'field list'",
        ]);
        $dbh = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->setMethods(['prepare'])->getMock();
        $dbh->method('prepare')->willReturn($stmt);

        $sql = 'select not_exists_field from user where state=?{{age: and age>?}} order by id';
        $builder = new Builder();
        $builder->build('age', 20);
        $builder->run($dbh, $sql, 1);
    }
}
