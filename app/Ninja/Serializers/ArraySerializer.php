<?php

namespace App\Ninja\Serializers;

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
    public function collection($resourceKey, array $data)
    {
        return $data;
    }

    /**
     * @param string $resourceKey
     * @param array  $data
     *
     * @return array
     */
    public function item($resourceKey, array $data)
    {
        return $data;
    }
}
