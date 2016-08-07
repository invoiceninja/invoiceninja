<?php namespace App\Ninja\Intents;

use Exception;

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
            throw new Exception(trans('texts.intent_not_found'));
        }

        $intent = $data->intents[0];
        $intentType = $intent->intent;
        
        $className = "App\\Ninja\\Intents\\{$intentType}Intent";

        if ( ! class_exists($className)) {
            throw new Exception(trans('texts.intent_not_supported'));
        }

        return (new $className($data));
    }

    public function process()
    {
        // do nothing by default
    }

}
