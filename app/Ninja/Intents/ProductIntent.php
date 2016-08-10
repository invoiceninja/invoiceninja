<?php namespace App\Ninja\Intents;

use Auth;
use Exception;


class ProductIntent extends BaseIntent
{
    public function __construct($state, $data)
    {
        $this->productRepo = app('App\Ninja\Repositories\ProductRepository');

        parent::__construct($state, $data);
    }
}
