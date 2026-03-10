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

namespace Chevere\Tests\src;

use Chevere\Container\Interfaces\ContainerInterface;
use RuntimeException;

final class AutoInjectOrderDependency
{
    public function __construct(
        ContainerInterface $container,
    ) {
        if (! $container->has('stdClassDependency')) {
            throw new RuntimeException('Container missing `stdClassDependency`');
        }
    }
}
