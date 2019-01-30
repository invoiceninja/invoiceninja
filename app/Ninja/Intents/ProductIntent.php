<?php

namespace App\Ninja\Intents;

class ProductIntent extends BaseIntent
{
    public function __construct($state, $data)
    {
        $this->productRepo = app('App\Ninja\Repositories\ProductRepository');

        parent::__construct($state, $data);
    }
}
