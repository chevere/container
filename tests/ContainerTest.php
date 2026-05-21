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
use Chevere\Tests\src\AutoInjectOrderDependency;
use Chevere\Tests\src\AutoInjectOrderRoot;
use Chevere\Tests\src\ClassWithObjectDefault;
use Chevere\Tests\src\ClassWithoutConstructor;
use Chevere\Tests\src\ClassWithPrimitiveDefault;
use Chevere\Tests\src\DependsOnClassWithoutConstructor;
use Chevere\Tests\src\InterfaceNamedDependency;
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
        $container->withAutoInject($dependencies);
        $this->assertFalse($container->has('one'));
        $this->assertFalse($container->has('two'));
        $this->assertFalse($container->has('extra'));
    }

    public function testWithAutoInjectMissingNested(): void
    {
        $dependencies = new Dependencies(NestedDependency::class);
        $this->expectNotToPerformAssertions();
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
        $keysBefore = $container->keys();
        $extract = $container->extract(StdClassDependency::class);
        $this->assertSame($keysBefore, $container->keys());
        $this->assertArrayHasKey('stdClass', $extract);
        $this->assertCount(1, $extract);
        $this->assertSame($stdClass, $extract['stdClass']);
        $this->assertArrayNotHasKey('foo', $extract);
        $this->assertArrayNotHasKey('bar', $extract);
    }

    public function testExtractDoesNotMutate(): void
    {
        $container = new Container(
            stdClass: new stdClass(),
        );
        $keysBefore = $container->keys();
        $container->extract(NestedDependency::class);
        $this->assertSame($keysBefore, $container->keys());
    }

    public function testExtractAutoInjectsMissingDependency(): void
    {
        $container = new Container();
        $extract = $container->extract(DependsOnClassWithoutConstructor::class);
        $this->assertArrayHasKey('classWithoutConstructor', $extract);
        $this->assertInstanceOf(ClassWithoutConstructor::class, $extract['classWithoutConstructor']);
        $this->assertFalse($container->has('classWithoutConstructor'));
        $instance = new DependsOnClassWithoutConstructor(...$extract);
        $this->assertInstanceOf(DependsOnClassWithoutConstructor::class, $instance);
    }

    public function testWithAutoInjectSelfContainerOrderFailureIsFeasible(): void
    {
        $dependencies = new Dependencies(AutoInjectOrderRoot::class);
        $base = new Container(
            stdClass: new stdClass()
        );
        // Self-reference is stored as an entry and can become stale across immutable clones.
        $container = $base->with(container: $base);
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            [autoInjectOrderDependency]: Failed to instantiate Chevere\Tests\src\AutoInjectOrderDependency: Container missing `stdClassDependency`
            PLAIN
        );
        $container->withAutoInject($dependencies);
    }

    public function testWithAutoInjectSelfContainerDependencyDeferredLast(): void
    {
        $dependencies = new Dependencies(AutoInjectOrderRoot::class);
        $base = new Container(
            stdClass: new stdClass()
        );
        $container = $base->with(container: $base);
        $firstPass = $container->withAutoInject(
            $dependencies,
            'autoInjectOrderDependency'
        );
        // Rebind to the current clone so container-aware dependencies see latest entries.
        $rebound = $firstPass->with(container: $firstPass);
        $resolved = $rebound->withAutoInject($dependencies);
        $this->assertTrue($resolved->has('stdClassDependency'));
        $this->assertTrue($resolved->has('autoInjectOrderDependency'));
        $this->assertInstanceOf(
            AutoInjectOrderDependency::class,
            $resolved->get('autoInjectOrderDependency')
        );
    }

    public function testWithAutoInjectSelfContainerDependencyDeferredWithoutRebindFails(): void
    {
        $dependencies = new Dependencies(AutoInjectOrderRoot::class);
        $base = new Container(
            stdClass: new stdClass()
        );
        $container = $base->with(container: $base);
        $firstPass = $container->withAutoInject(
            $dependencies,
            'autoInjectOrderDependency'
        );
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            [autoInjectOrderDependency]: Failed to instantiate Chevere\Tests\src\AutoInjectOrderDependency: Container missing `stdClassDependency`
            PLAIN
        );
        $firstPass->withAutoInject($dependencies);
    }

    public function testObjectDefaultIsSkippedByAutoInject(): void
    {
        $container = new Container();
        $args = $container->extract(ClassWithObjectDefault::class);
        $this->assertArrayNotHasKey('context', $args);
        $instance = new ClassWithObjectDefault(...$args);
        $this->assertInstanceOf(stdClass::class, $instance->context);
    }

    public function testPrimitiveDefaultIsSkippedByAutoInject(): void
    {
        $context = new stdClass();
        $container = new Container(context: $context); // $channel intentionally absent
        $args = $container->extract(ClassWithPrimitiveDefault::class);
        $this->assertSame($context, $args['context']);
        $this->assertArrayNotHasKey('channel', $args);
        $instance = new ClassWithPrimitiveDefault(...$args);
        $this->assertSame('default', $instance->channel);
    }

    public function testExplicitBindingIsUsedEvenWhenParameterHasDefault(): void
    {
        $context = new stdClass();
        $container = new Container(context: $context);
        $args = $container->extract(ClassWithObjectDefault::class);
        $this->assertSame($context, $args['context']);
        $instance = new ClassWithObjectDefault(...$args);
        $this->assertSame($context, $instance->context);
    }

    public function testWithAutoInjectInterfaceDependencyIgnored(): void
    {
        $dependencies = new Dependencies(InterfaceNamedDependency::class);
        $container = (new Container())->withAutoInject($dependencies);
        $this->assertCount(0, $container);
    }

    public function testWithAutoInjectClassWithoutConstructor(): void
    {
        $dependencies = new Dependencies(DependsOnClassWithoutConstructor::class);
        $container = (new Container())->withAutoInject($dependencies);
        $this->assertTrue($container->has('classWithoutConstructor'));
        $this->assertInstanceOf(
            ClassWithoutConstructor::class,
            $container->get('classWithoutConstructor')
        );
    }
}
