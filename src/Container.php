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

namespace Chevere\Container;

use Chevere\Container\Exceptions\ContainerException;
use Chevere\Container\Exceptions\ContainerNotFoundException;
use Chevere\Container\Interfaces\ContainerInterface;
use Chevere\Container\Interfaces\DependenciesInterface;
use Chevere\DataStructure\Map;
use Chevere\DataStructure\Traits\MapTrait;
use Chevere\Parameter\Interfaces\ObjectParameterInterface;
use Chevere\Parameter\Interfaces\ParametersInterface;
use Chevere\Parameter\Interfaces\TypeInterface;
use ReflectionMethod;
use Throwable;
use function Chevere\Parameter\reflectionToParameters;

final class Container implements ContainerInterface
{
    /**
     * @template-use MapTrait<mixed>
     */
    use MapTrait;

    public function __construct(
        mixed ...$entries
    ) {
        $this->map = new Map(...$entries);
    }

    public function get(string $id): mixed
    {
        if (! $this->has($id)) {
            throw new ContainerNotFoundException();
        }

        try {
            return $this->map->get($id);
            // @codeCoverageIgnoreStart
        } catch (Throwable) {
            throw new ContainerException();
            // @codeCoverageIgnoreEnd
        }
    }

    public function has(string $id): bool
    {
        return $this->map->has($id);
    }

    public function withAutoInject(
        DependenciesInterface $dependencies,
        string ...$ignore
    ): ContainerInterface {
        $new = clone $this;
        $new->autoInject($dependencies->parameters(), [], ...$ignore);

        return $new;
    }

    public function with(mixed ...$entry): ContainerInterface
    {
        $new = clone $this;
        $new->put(...$entry);

        return $new;
    }

    public function without(string ...$entry): ContainerInterface
    {
        $new = clone $this;
        $new->map = $new->map->without(...$entry);

        return $new;
    }

    public function extract(string $className): array
    {
        $new = clone $this;
        $reflection = new ReflectionMethod($className, '__construct');
        $parameters = reflectionToParameters($reflection);
        $new->autoInject($parameters, []);
        $extra = array_diff($new->keys(), $parameters->keys());

        return iterator_to_array(
            $new->without(...$extra)
        );
    }

    /**
     * @param array<string> $resolving
     */
    private function autoInject(
        ParametersInterface $parameters,
        array $resolving = [],
        string ...$ignore
    ): void {
        $missingDeps = array_diff(
            $parameters->keys(),
            $this->keys(),
            $ignore
        );
        $failures = [];
        foreach ($missingDeps as $missingDep) {
            if ($parameters->optionalKeys()->contains($missingDep)) {
                continue;
            }
            if (in_array($missingDep, $resolving, true)) {
                $failures[] = [$missingDep, "Circular dependency detected while resolving `{$missingDep}`"];

                continue;
            }
            $arguments = [];
            $parameter = $parameters->has($missingDep)
                ? $parameters->get($missingDep)
                : null;
            if (! ($parameter instanceof ObjectParameterInterface)) {
                $failures[] = [$missingDep, "Parameter {$missingDep} is not an object type"];

                continue;
            }
            $primitive = $parameter->type()->primitive();
            if ($primitive !== TypeInterface::PRIMITIVE_CLASS_NAME) {
                continue;
            }
            $className = $parameter->type()->typeHinting();
            if (method_exists($className, '__construct')) {
                $reflection = new ReflectionMethod($className, '__construct');
                $reflectionParameters = reflectionToParameters($reflection);

                try {
                    $this->autoInject(
                        $reflectionParameters,
                        [...$resolving, $missingDep],
                        ...$ignore
                    );
                    $arguments = $reflectionParameters(...iterator_to_array($this))->toArray();
                } catch (Throwable $e) {
                    $failures[] = [
                        $missingDep,
                        "Failed to resolve dependencies for `{$className}`: {$e->getMessage()}",
                    ];

                    continue;
                }
            }

            try {
                $this->put(
                    ...[
                        $missingDep => new $className(...$arguments),
                    ]
                );
            } catch (Throwable $e) {
                $failures[] = [$missingDep, "Failed to instantiate {$className}: {$e->getMessage()}"];
            }
        }
        if ($failures !== []) {
            $lines = [];
            foreach ($failures as [$param, $message]) {
                $lines[] = "[{$param}]: {$message}";
            }

            throw new ContainerException(implode("\n", $lines));
        }
    }

    private function put(mixed ...$entry): void
    {
        foreach ($entry as $name => $value) {
            $this->map = $this->map->withPut(strval($name), $value);
        }
    }
}
