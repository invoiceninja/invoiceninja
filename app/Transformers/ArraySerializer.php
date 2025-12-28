<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Transformers;

use League\Fractal\Serializer\ArraySerializer as FractalArraySerializer;

/**
 * Class ArraySerializer.
 */
class ArraySerializer extends FractalArraySerializer
{
    /**
     * @param string $resourceKey
     * @param array  $data
     *
     * @return array
     */
    public function collection(?string $resourceKey, array $data): array
    {
        return $data;
    }

    /**
     * @param string $resourceKey
     * @param array  $data
     *
     * @return array
     */
    public function item(?string $resourceKey, array $data): array
    {
        return $data;
    }
}
