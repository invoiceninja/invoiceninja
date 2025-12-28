<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Export\Decorators;

interface DecoratorInterface
{
    public function transform(string $key, mixed $entity): mixed;
}
