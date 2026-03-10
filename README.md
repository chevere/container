# Container

![Chevere](chevere.svg)

[![Build](https://img.shields.io/github/actions/workflow/status/chevere/container/test.yml?branch=1.0&style=flat-square)](https://github.com/chevere/container/actions)
![Code size](https://img.shields.io/github/languages/code-size/chevere/container?style=flat-square)
[![Apache-2.0](https://img.shields.io/github/license/chevere/container?style=flat-square)](LICENSE)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%209-blueviolet?style=flat-square)](https://phpstan.org/)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat-square&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fchevere%2Fcontainer%2F1.0)](https://dashboard.stryker-mutator.io/reports/github.com/chevere/container/1.0)

[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=chevere_container&metric=alert_status)](https://sonarcloud.io/dashboard?id=chevere_container)
[![Maintainability Rating](https://sonarcloud.io/api/project_badges/measure?project=chevere_container&metric=sqale_rating)](https://sonarcloud.io/dashboard?id=chevere_container)
[![Reliability Rating](https://sonarcloud.io/api/project_badges/measure?project=chevere_container&metric=reliability_rating)](https://sonarcloud.io/dashboard?id=chevere_container)
[![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=chevere_container&metric=security_rating)](https://sonarcloud.io/dashboard?id=chevere_container)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=chevere_container&metric=coverage)](https://sonarcloud.io/dashboard?id=chevere_container)
[![Technical Debt](https://sonarcloud.io/api/project_badges/measure?project=chevere_container&metric=sqale_index)](https://sonarcloud.io/dashboard?id=chevere_container)
[![CodeFactor](https://www.codefactor.io/repository/github/chevere/container/badge)](https://www.codefactor.io/repository/github/chevere/container)

## Summary

**Chevere Container** is a minimalist PSR-11 dependency injection container. It provides `get` and `has` for service container-interop, along with immutable `with` and `without` methods for modifying container entries.

It features automatic dependency resolution and injection. The container can automatically instantiate and inject class dependencies based on their constructor type hints. It also provides a `Dependencies` utility to collect, validate, and manage dependencies from multiple classes, ensuring type compatibility across the dependency graph.

## Installing

Container is available through [Packagist](https://packagist.org/packages/chevere/container) and the repository source is at [chevere/container](https://github.com/chevere/container).

```sh
composer require chevere/container
```

## Container usage

Create a Container by passing the known dependencies.

```php
use Chevere\Container\Container;

$container = new Container(
    database: $database,
    cipher: $cipher,
    //...
);
```

### Checking entries

Use `has` to check if an entry exists in the container.

```php
$container->has('database'); // true
$container->has('logger'); // false
```

### Retrieving entries

Use `get` to retrieve an entry from the container. It will throw `ContainerNotFoundException` if the entry is not found.

```php
$database = $container->get('database');
```

### Adding entries

Use `with` to add or replace entries in the container.

```php
$newContainer = $container->with(logger: $logger);
```

### Removing entries

Use `without` to remove entries from the container.

```php
$newContainer = $container->without('cipher');
```

## Auto-injection

The container can automatically resolve and inject dependencies.

### Resolving dependencies

Use `withAutoInject` to get a new container instance with resolved dependencies.

```php
$containerWithServices = $container->withAutoInject($dependencies);
```

This will automatically create instances for services like `MyController` and `MyMiddleware` if they are not already in the container, resolving their own dependencies recursively.

### Extracting arguments

Use `extract` to get the constructor arguments for a class from the container.

```php
$arguments = $containerWithServices->extract(MyController::class);
$controller = new MyController(...$arguments);
```

## Dependencies

The `Dependencies` class is used to collect constructor dependencies from multiple classes. It helps to identify required services and validates against type compatibility.

```php
use Chevere\Container\Dependencies;

$dependencies = new Dependencies(
    MyController::class,
    MyMiddleware::class,
);
```

### Reading classes

Use `classes` to get a list of component classes in the collection.

```php
$classNames = $dependencies->classes();
```

### Adding more dependencies

Use `withClass` to add more class dependencies to the collection. Returns a new instance with the specified classes added.

```php
$newDependencies = $dependencies->withClass(
    AnotherController::class,
    AnotherMiddleware::class,
);
```

### Accessing parameters

Use `parameters` to get a ParametersInterface instance reflecting all dependencies as [Parameters](https://chevere.org/packages/parameter).

```php
$parameters = $dependencies->parameters();
```

### Checking class dependencies

Use `has` to check if a specific class name defines dependencies.

```php
$dependencies->has(MyController::class); // true
$dependencies->has(UnknownClass::class); // false
```

### Retrieving class parameters

Use `get` to retrieve the parameters (dependencies) for a known class name.

```php
$controllerParams = $dependencies->get(MyController::class);
```

### Extracting constructor arguments

Use `extract` to get the typed constructor arguments for a class from a container.

```php
$arguments = $dependencies->extract(MyController::class, $container);
$controller = new MyController(...$arguments);
```

### Identifying dependency requirer

Use `requirer` to identify which class declared a specific dependency.

```php
$className = $dependencies->requirer('database');
```

### Validating container dependencies

Use `assert` to check if a container has all the required dependencies. It will throw a `LogicException` if any dependency is missing or if there's a type mismatch.

```php
$dependencies->assert($container);
```

## Documentation

Documentation is available at [chevere.org](https://chevere.org/packages/container).

## License

Copyright [Rodolfo Berrios A.](https://rodolfoberrios.com/)

Chevere is licensed under the Apache License, Version 2.0. See [LICENSE](LICENSE) for the full license text.

Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the specific language governing permissions and limitations under the License.
