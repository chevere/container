<?php

/*
 * This file is part of Chevere.
 *
 * (c) Rodolfo Berrios <rodolfo@chevere.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Chevere\Tests;

use Chevere\Container\Container;
use Chevere\Container\Dependencies;
use Chevere\Container\Exceptions\ContainerException;
use Chevere\Container\Exceptions\ContainerNotFoundException;
use Chevere\Tests\src\NestedDependency;
use Chevere\Tests\src\StdClassDependency;
use Chevere\Tests\src\ValuesDependency;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ContainerTest extends TestCase
{
    public function testGet(): void
    {
        $container = new Container(foo: 'bar');
        $this->assertSame('bar', $container->get('foo'));
        $this->expectException(ContainerNotFoundException::class);
        $container->get('baz');
    }

    public function testHas(): void
    {
        $container = new Container(foo: 'bar');
        $this->assertTrue($container->has('foo'));
        $this->assertFalse($container->has('baz'));
    }

    public function testWith(): void
    {
        $container = new Container(foo: 'bar');
        $object = new stdClass();
        $newContainer = $container->with(foo: $object);
        $this->assertNotSame($container, $newContainer);
        $this->assertTrue($newContainer->has('foo'));
        $this->assertSame($object, $newContainer->get('foo'));
    }

    public function testWithout(): void
    {
        $container = new Container(foo: 'bar');
        $this->assertTrue($container->has('foo'));
        $newContainer = $container->without('foo');
        $this->assertNotSame($container, $newContainer);
        $this->assertFalse($newContainer->has('foo'));
    }

    public function testWithAutoInject(): void
    {
        $dependencies = new Dependencies(
            NestedDependency::class,
            ValuesDependency::class
        );
        $ignore = ['one', 'two', 'extra'];
        $stdClass = new stdClass();
        $container = new Container(
            stdClass: $stdClass,
        );
        $with = $container->withAutoInject($dependencies, ...$ignore);
        $this->assertNotSame($container, $with);
        $this->assertNotSame(
            spl_object_id($container),
            spl_object_id($with)
        );
        $this->assertNotSame($container, $with);
        $this->assertFalse($with->has('one'));
        $this->assertFalse($with->has('two'));
        $this->assertTrue($with->has('stdClassDependency'));
        $this->assertTrue($with->has('stdClass'));
        $this->assertSame(
            $stdClass,
            $with->get('stdClass')
        );
        $this->assertInstanceOf(
            StdClassDependency::class,
            $with->get('stdClassDependency')
        );
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            [one]: Parameter one is not an object type
            [two]: Parameter two is not an object type
            PLAIN
        );
        $container->withAutoInject($dependencies);
    }

    public function testWithAutoInjectMissingNested(): void
    {
        $dependencies = new Dependencies(NestedDependency::class);
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            [stdClassDependency]: Failed to resolve dependencies for `Chevere\Tests\src\StdClassDependency`: Missing required argument(s): `stdClass`
            PLAIN
        );
        (new Container())->withAutoInject($dependencies);
    }

    public function testExtract(): void
    {
        $stdClass = new stdClass();
        $container = new Container(
            stdClass: $stdClass,
            foo: 'bar',
            bar: 'baz'
        );
        $extract = $container->extract(StdClassDependency::class);
        $this->assertArrayHasKey('stdClass', $extract);
        $this->assertCount(1, $extract);
        $this->assertSame($stdClass, $extract['stdClass']);
        $this->assertArrayNotHasKey('foo', $extract);
        $this->assertArrayNotHasKey('bar', $extract);
    }
}
