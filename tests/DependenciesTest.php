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
use Chevere\Tests\src\StdClassDependency;
use Chevere\Tests\src\ValueDefaultDependency;
use Chevere\Tests\src\ValueIntDependency;
use Chevere\Tests\src\ValuesDependency;
use Chevere\Tests\src\ValueStringDependency;
use InvalidArgumentException;
use LogicException;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use TypeError;

final class DependenciesTest extends TestCase
{
    public function testEmpty(): void
    {
        $dependencies = new Dependencies();
        $this->assertCount(0, $dependencies->parameters());
        $this->assertFalse($dependencies->has('className'));
        $this->assertSame(
            [],
            $dependencies->extract(
                'className',
                new Container(
                    key: 'value',
                )
            )
        );
        $this->assertSame([], $dependencies->classes());
        $this->expectException(OutOfBoundsException::class);
        $dependencies->get('className');
    }

    public function testWithClass(): void
    {
        $dependencies = new Dependencies();
        $with = $dependencies->withClass(StdClassDependency::class);
        $this->assertNotSame($dependencies, $with);
        $this->assertTrue($with->has(StdClassDependency::class));
        $this->assertSame([StdClassDependency::class], $with->classes());
        $this->assertEquals(
            new Dependencies(StdClassDependency::class),
            (new Dependencies())->withClass(StdClassDependency::class)
        );
        $this->assertEquals(
            new Dependencies(StdClassDependency::class),
            (new Dependencies(StdClassDependency::class))
                ->withClass(StdClassDependency::class, StdClassDependency::class)
        );
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class `not a class` does not exist');
        new Dependencies('not a class');
    }

    public function testEmptyRequirer(): void
    {
        $dependencies = new Dependencies();
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Dependency `` not defined');
        $dependencies->requirer('');
    }

    public function testAssert(): void
    {
        $this->expectException(LogicException::class);
        $fileLine = $this->getDependentFileLine(ValuesDependency::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            - [1]: Missing argument `one` as previously defined by `Chevere\Tests\src\ValuesDependency` in {$fileLine}
            PLAIN
        );
        $this->expectExceptionMessage(
            <<<PLAIN
            - [2]: Argument `two` provided as `int` is not compatible with `string` as previously defined by `Chevere\Tests\src\ValuesDependency` in {$fileLine}
            PLAIN
        );
        $fileLine = $this->getDependentFileLine(ValueIntDependency::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            - [3]: Argument `value` provided as `float` is not compatible with `int` as previously defined by `Chevere\Tests\src\ValueIntDependency` in {$fileLine}
            PLAIN
        );
        $dependencies = new Dependencies(
            ValuesDependency::class,
            ValueIntDependency::class,
        );
        $dependencies->assert(
            new Container(
                two: 2,
                value: 10.2
            )
        );
    }

    public function testIncompatibleDependencies(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage(
            'Variable `$value` defined as `int` is not compatible with `string` as previously defined by `Chevere\Tests\src\ValueStringDependency` in '
        );
        new Dependencies(
            ValueStringDependency::class,
            ValueIntDependency::class
        );
    }

    public function testExtract(): void
    {
        $dependencies = new Dependencies(
            ValueIntDependency::class
        );
        $extracted = $dependencies->extract(
            ValueIntDependency::class,
            new Container(value: 420)
        );
        $this->assertSame(
            [
                'value' => 420,
            ],
            $extracted
        );
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Dependency `value` not defined in container');
        $dependencies->extract(
            ValueIntDependency::class,
            new Container()
        );
    }

    public function testDefaultValueNoBinds(): void
    {
        $container = new Container();
        $dependencies = new Dependencies(ValueDefaultDependency::class);
        $dependencies->assert($container);
        $object = new ValueDefaultDependency(
            ...$dependencies->extract(ValueDefaultDependency::class, $container)
        );
        $this->assertSame(1, $object->one);
    }

    public function testDefaultValueOverride(): void
    {
        $container = new Container(one: 101);
        $dependencies = new Dependencies(ValueDefaultDependency::class);
        $dependencies->assert($container);
        $object = new ValueDefaultDependency(
            ...$dependencies->extract(ValueDefaultDependency::class, $container)
        );
        $this->assertSame(101, $object->one);
    }

    private function getDependentFileLine(string $className): string
    {
        $reflector = new ReflectionMethod($className, '__construct');

        return $reflector->getFileName() . ':' . $reflector->getStartLine();
    }
}
