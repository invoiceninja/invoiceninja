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

        $paramName = $this->entityType . 's';
        $publicId = $this->$paramName ?: (Input::get('public_id') ?: Input::get('id'));
        
        if ( ! $publicId) {
            return null;
        } 
        
        $class = Utils::getEntityClass($this->entityType);
        $this->entity = $class::scope($publicId)->withTrashed()->firstOrFail();
        
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
