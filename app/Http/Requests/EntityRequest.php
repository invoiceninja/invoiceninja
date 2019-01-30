<?php

namespace App\Http\Requests;

use App\Libraries\HistoryUtils;
use App\Models\Contact;
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

        $class = EntityModel::getClassName($this->entityType);

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
            $field = $this->entityType;
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

        //Support Client Portal Scopes
        $accountId = false;

        if(Input::get('account_id'))
            $accountId = Input::get('account_id');
        elseif($contact = Contact::getContactIfLoggedIn())
            $accountId = $contact->account->id;

        if (method_exists($class, 'trashed')) {
            $this->entity = $class::scope($publicId, $accountId)->withTrashed()->firstOrFail();
        } else {
            $this->entity = $class::scope($publicId, $accountId)->firstOrFail();

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
            return $this->user()->can('createEntity', $this->entityType);
        }
    }

    public function rules()
    {
        return [];
    }
}
