<?php namespace App\Ninja\Intents;

use stdClass;
use Exception;
use App\Libraries\CurlUtils;

class BaseIntent
{
    protected $state;
    protected $parameters;

    public function __construct($state, $data)
    {
        $this->state = $state ?: new stdClass;
        $this->data = $data;
    }

    public static function createIntent($state, $data)
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

        return (new $className($state, $data));
    }

    public function process()
    {
        // do nothing by default
    }

    public function addState($entities)
    {
        var_dump($this->state);
        if (isset($this->state->current)) {
            $this->state->previous = $this->state->current;
        }


        $this->state->current = $entities;
    }

    public function getState()
    {
        return $this->state;
    }

    protected function parseClient()
    {
        $clientRepo = app('App\Ninja\Repositories\ClientRepository');

        $client = false;

        foreach ($this->data->entities as $param) {
            if ($param->type == 'Client') {
                $client = $clientRepo->findPhonetically($param->entity);
            }
        }

        return $client;
    }

    protected function parseInvoiceItems()
    {
        $productRepo = app('App\Ninja\Repositories\ProductRepository');

        $invoiceItems = [];

        foreach ($this->data->compositeEntities as $entity) {
            if ($entity->parentType == 'InvoiceItem') {
                $product = false;
                $qty = 1;
                foreach ($entity->children as $child) {
                    if ($child->type == 'Product') {
                        $product = $productRepo->findPhonetically($child->value);
                    } else {
                        $qty = $child->value;
                    }
                }

                $item = $product->toArray();
                $item['qty'] = $qty;
                $invoiceItems[] = $item;
            }
        }

        return $invoiceItems;
    }

}
