<?php

namespace App\Ninja\Intents;

use App\Libraries\Skype\SkypeResponse;
use App\Models\Client;
use Exception;
use stdClass;

class BaseIntent
{
    protected $state;
    protected $parameters;
    protected $fieldMap = [];

    public function __construct($state, $data)
    {
        //if (true) {
        if (! $state || is_string($state)) {
            $state = new stdClass();
            foreach (['current', 'previous'] as $reference) {
                $state->$reference = new stdClass();
                $state->$reference->entityType = false;
                foreach ([ENTITY_INVOICE, ENTITY_CLIENT, ENTITY_INVOICE_ITEM] as $entityType) {
                    $state->$reference->$entityType = [];
                }
            }
        }

        $this->state = $state;
        $this->data = $data;

        // If they're viewing a client set it as the current state
        if (! $this->hasField('Filter', 'all')) {
            $url = url()->previous();
            preg_match('/clients\/(\d*)/', $url, $matches);
            if (count($matches) >= 2) {
                if ($client = Client::scope($matches[1])->first()) {
                    $this->state->current->client = $client;
                }
            }
        }

        //var_dump($state);
    }

    public static function createIntent($platform, $state, $data)
    {
        if (! count($data->intents)) {
            throw new Exception(trans('texts.intent_not_found'));
        }

        $intent = $data->intents[0]->intent;
        $entityType = false;

        foreach ($data->entities as $entity) {
            if ($entity->type === 'EntityType') {
                $entityType = rtrim($entity->entity, 's');
                break;
            }
        }

        if ($state && ! $entityType) {
            $entityType = $state->current->entityType;
        }
        $entityType = $entityType ?: 'client';
        $entityType = ucwords(strtolower($entityType));
        if ($entityType == 'Recurring') {
            $entityType = 'RecurringInvoice';
        }
        $intent = str_replace('Entity', $entityType, $intent);

        if ($platform == BOT_PLATFORM_WEB_APP) {
            $className = "App\\Ninja\\Intents\\WebApp\\{$intent}Intent";
        } else {
            $className = "App\\Ninja\\Intents\\{$intent}Intent";
        }

        if (! class_exists($className)) {
            throw new Exception(trans('texts.intent_not_supported'));
        }

        return new $className($state, $data);
    }

    protected function getField($field)
    {
        foreach ($this->data->entities as $entity) {
            if ($entity->type === $field) {
                return $entity->entity;
            }
        }

        return false;
    }

    protected function getFields($field)
    {
        $data = [];

        foreach ($this->data->entities as $entity) {
            if ($entity->type === $field) {
                $data[] = $entity->entity;
            }
        }

        return $data;
    }

    protected function loadStates($entityType)
    {
        $states = array_filter($this->getFields('Filter'), function($state) {
            return in_array($state, [STATUS_ACTIVE, STATUS_ARCHIVED, STATUS_DELETED]);
        });

        if (count($states) || $this->hasField('Filter', 'all')) {
            session(['entity_state_filter:' . $entityType => join(',', $states)]);
        }
    }

    protected function hasField($field, $value = false)
    {
        $fieldValue = $this->getField($field);

        if ($value) {
            return $fieldValue && $fieldValue == $value;
        } else {
            return $fieldValue ? true : false;
        }
    }

    public function process()
    {
        throw new Exception(trans('texts.intent_not_supported'));
    }

    public function setStateEntities($entityType, $entities)
    {
        if (! is_array($entities)) {
            $entities = [$entities];
        }

        $state = $this->state;

        $state->previous->$entityType = $state->current->$entityType;
        $state->current->$entityType = $entities;
    }

    public function setStateEntityType($entityType)
    {
        $state = $this->state;

        if ($state->current->entityType == $entityType) {
            return;
        }

        $state->previous->entityType = $state->current->entityType;
        $state->current->entityType = $entityType;
    }

    public function stateEntities($entityType)
    {
        return $this->state->current->$entityType;
    }

    public function stateEntity($entityType)
    {
        $entities = $this->state->current->$entityType;

        return count($entities) ? $entities[0] : false;
    }

    public function previousStateEntities($entityType)
    {
        return $this->state->previous->$entityType;
    }

    public function stateEntityType()
    {
        return $this->state->current->entityType;
    }

    public function getState()
    {
        return $this->state;
    }

    protected function requestClient()
    {
        $clientRepo = app('App\Ninja\Repositories\ClientRepository');
        $client = false;

        foreach ($this->data->entities as $param) {
            if ($param->type == 'Name') {
                $param->type = rtrim($param->type, ' \' s');
                $client = $clientRepo->findPhonetically($param->entity);
            }
        }

        if (! $client) {
            $client = $this->state->current->client;
        }

        return $client;
    }

    protected function requestInvoice()
    {
        $invoiceRepo = app('App\Ninja\Repositories\InvoiceRepository');
        $invoice = false;

        foreach ($this->data->entities as $param) {
            if ($param->type == 'builtin.number') {
                return $invoiceRepo->findPhonetically($param->entity);
            }
        }

        return false;
    }

    protected function requestFields()
    {
        $data = [];

        if (! isset($this->data->compositeEntities)) {
            return [];
        }

        foreach ($this->data->compositeEntities as $compositeEntity) {
            if ($compositeEntity->parentType != 'FieldValuePair') {
                continue;
            }

            $field = false;
            $value = false;

            foreach ($compositeEntity->children as $child) {
                if ($child->type == 'Field') {
                    $field = $child->value;
                } elseif ($child->type == 'Value') {
                    $value = $child->value;
                }
            }

            if ($field && $value) {
                $field = $this->processField($field);
                $value = $this->processValue($value);

                $data[$field] = $value;
            }
        }

        foreach ($this->fieldMap as $key => $value) {
            if (isset($data[$key])) {
                $data[$value] = $data[$key];
                unset($data[$key]);
            }
        }

        return $data;
    }

    protected function requestFieldsAsString($fields)
    {
        $str = '';

        foreach ($this->requestFields() as $field => $value) {
            if (in_array($field, $fields)) {
                $str .= $field . '=' . urlencode($value) . '&';
            }
        }

        $str = rtrim($str, '?');
        $str = rtrim($str, '&');

        return $str;
    }

    protected function processField($field)
    {
        $field = str_replace(' ', '_', $field);

        /* Shouldn't be need any more
        if (strpos($field, 'date') !== false) {
            $field .= '_sql';
        }
        */

        return $field;
    }

    protected function processValue($value)
    {
        // look for LUIS pre-built entity matches
        foreach ($this->data->entities as $entity) {
            if ($entity->entity === $value) {
                if ($entity->type == 'builtin.datetime.date') {
                    $value = $entity->resolution->date;
                    $value = str_replace('XXXX', date('Y'), $value);
                }
            }
        }

        return $value;
    }

    protected function createResponse($type, $content)
    {
        $response = new SkypeResponse($type);

        if (is_string($content)) {
            $response->setText($content);
        } else {
            if ($content instanceof \Illuminate\Database\Eloquent\Collection) {
                // do nothing
            } elseif (! is_array($content)) {
                $content = [$content];
            }

            foreach ($content as $item) {
                $response->addAttachment($item);
            }
        }

        return json_encode($response);
    }
}
