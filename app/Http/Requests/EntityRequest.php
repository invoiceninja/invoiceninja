<?php namespace App\Http\Requests;

use App\Http\Requests\Request;
use Input;
use Utils;

class EntityRequest extends Request {

    protected $entityType;
    private $entity;

    public function entity()
    {
        if ($this->entity) {
            return $this->entity;
        }

        // The entity id can appear as invoices, invoice_id, public_id or id
        $publicId = false;
        foreach (['_id', 's'] as $suffix) {
            $field = $this->entityType . $suffix;
            if ($this->$field) {
                $publicId= $this->$field;
            }
        }
        if ( ! $publicId) {
            $publicId = Input::get('public_id') ?: Input::get('id');
        }
        if ( ! $publicId) {
            return null;
        }

        $class = Utils::getEntityClass($this->entityType);
        \Log::info('entity ' . $this->entityType . ' - ' . $publicId);
        if (method_exists($class, 'withTrashed')) {
            \Log::info('has withTrashed')
            $this->entity = $class::scope($publicId)->withTrashed()->firstOrFail();
        } else {
            \Log::info('does not have withTrashed')
            $this->entity = $class::scope($publicId)->firstOrFail();
        }
        \Log::info($this->entity);
        return $this->entity;
    }

    public function authorize()
    {
        if ($this->entity()) {
            return $this->user()->can('view', $this->entity());
        } else {
            return $this->user()->can('create', $this->entityType);
        }
    }

    public function rules()
    {
        return [];
    }
}
