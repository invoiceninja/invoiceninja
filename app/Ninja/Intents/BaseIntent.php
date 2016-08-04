<?php namespace App\Ninja\Intents;


class BaseIntent
{
    protected $parameters;

    public function __construct($parameters)
    {
        $this->parameters = $parameters;
    }

    public static function createIntent($intentType, $parameters)
    {
        $className = "App\\Ninja\\Intents\\{$intentType}Intent";
        return new $className($parameters);
    }

    public function process()
    {
        // do nothing by default
    }

}
