<?php namespace App\Ninja\Intents;


class BaseIntent
{
    protected $parameters;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public static function createIntent($data)
    {
        if ( ! count($data->intents)) {
            return false;
        }

        $intent = $data->intents[0];
        $intentType = $intent->intent;

        $className = "App\\Ninja\\Intents\\{$intentType}Intent";
        return new $className($data);
    }

    public function process()
    {
        // do nothing by default
    }

}
