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

namespace Chevere\Container\Interfaces;

use Chevere\Parameter\Interfaces\ParametersAccessInterface;
use Chevere\Parameter\Interfaces\ParametersInterface;
use Psr\Container\ContainerInterface;

/**
 * Describes the component in charge of defining the collection of Router
 * dependencies which are the parameters required by participants of the
 * request handling.
 *
 * It refers to the parameters indicated at the `__construct` method of the
 * controller and middleware classes used in routing.
 */
interface DependenciesInterface extends ParametersAccessInterface
{
    /**
     * Return an instance with the specified class name(s) added.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the dependencies from the specified class name(s).
     */
    public function withClass(string ...$className): self;

    /**
     * Provides access to the Parameters instance reflecting the router dependencies.
     */
    public function parameters(): ParametersInterface;

    /**
     * Indicates whether the given class name defines dependencies.
     */
    public function has(string $className): bool;

    /**
     * Provides access to the parameters (dependencies) for a known class name.
     */
    public function get(string $className): ParametersInterface;

    /**
     * Provides access to the typed arguments for the given class name and container.
     *
     * @return array<string, mixed> Constructor arguments, taken from the container
     */
    public function extract(string $className, ContainerInterface $container): array;

    /**
     * Indicates the class name which declared the given dependency.
     */
    public function requirer(string $name): string;

    /**
     * Asserts that the given container has all dependencies.
     */
    public function assert(ContainerInterface $container): void;

    /**
     * Provides access to the class names of all dependencies defined in this collection.
     *
     * @return array<string> A vector of dependency class names.
     */
    public function classes(): array;
}
