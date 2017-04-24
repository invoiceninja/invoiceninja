<?php

namespace App\Http\Requests;

use App\Libraries\HistoryUtils;
use App\Models\EntityModel;
use Input;
use Utils;

class EntityRequest extends Request
{
    protected $entityType;
    private $entity;

    public function entity()
    {
        if ($this->entity) {
            return $this->entity;
        }

        // The entity id can appear as invoices, invoice_id, public_id or id
        $publicId = false;
        $field = $this->entityType . '_id';
        if (! empty($this->$field)) {
            $publicId = $this->$field;
        }
        if (! $publicId) {
            $field = Utils::pluralizeEntityType($this->entityType);
            if (! empty($this->$field)) {
                $publicId = $this->$field;
            }
        }
        if (! $publicId) {
            $publicId = Input::get('public_id') ?: Input::get('id');
        }
        if (! $publicId) {
            return null;
        }

        $class = EntityModel::getClassName($this->entityType);

        if (method_exists($class, 'trashed')) {
            $this->entity = $class::scope($publicId)->withTrashed()->firstOrFail();
        } else {
            $this->entity = $class::scope($publicId)->firstOrFail();
        }

        return $this->entity;
    }

    public function setEntity($entity)
    {
        $this->entity = $entity;
    }

    public function authorize()
    {
        if ($this->entity()) {
            if ($this->user()->can('view', $this->entity())) {
                HistoryUtils::trackViewed($this->entity());

                return true;
            }
        } else {
            return $this->user()->can('create', $this->entityType);
        }
    }

    public function rules()
    {
        return [];
    }
}
