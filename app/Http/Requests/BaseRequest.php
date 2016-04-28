<?php namespace App\Http\Requests;

use App\Http\Requests\Request;
use Input;
use Utils;

class BaseRequest extends Request {

    protected $entityType;
    private $entity;

    public function entity() 
    {
        if ($this->entity) {
            return $this->entity;
        }
        
        //dd($this->clients);
        $publicId = Input::get('public_id') ?: Input::get('id');
        
        if ( ! $publicId) {
            return null;
        } 
        
        $class = Utils::getEntityClass($this->entityType);
        $this->entity = $class::scope($publicId)->withTrashed()->firstOrFail();
        
        return $this->entity;
    }
}
