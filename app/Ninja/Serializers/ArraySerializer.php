<?php namespace App\Ninja\Serializers;

use League\Fractal\Serializer\ArraySerializer as FractalArraySerializer;

class ArraySerializer extends FractalArraySerializer
{
    public function collection($resourceKey, array $data)
    {
        return $data;
        //return ($resourceKey && $resourceKey !== 'data') ? array($resourceKey => $data) : $data;
    }

    public function item($resourceKey, array $data)
    {
        return $data;
        //return ($resourceKey && $resourceKey !== 'data') ? array($resourceKey => $data) : $data;
    }
}
