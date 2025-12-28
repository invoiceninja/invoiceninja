<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Export\Decorators;

class ProjectDecorator implements DecoratorInterface
{
    public function transform(string $key, mixed $entity): mixed
    {
        return 'Payment Decorator';
    }
}
