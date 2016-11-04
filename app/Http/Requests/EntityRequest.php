<?php namespace App\Http\Requests;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Input;
use Utils;
use App\Libraries\HistoryUtils;

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
        $field = $this->entityType . '_id';
        if ( ! empty($this->$field)) {
            $publicId = $this->$field;
        }
        if ( ! $publicId) {
            $field = Utils::pluralizeEntityType($this->entityType);
            if ( ! empty($this->$field)) {
                $publicId = $this->$field;
            }
        }
        if ( ! $publicId) {
            $publicId = Input::get('public_id') ?: Input::get('id');
        }
        if ( ! $publicId) {
            return null;
        }

        $class = Utils::getEntityClass($this->entityType);

        try {

            if (method_exists($class, 'trashed')) {
                $this->entity = $class::scope($publicId)->withTrashed()->firstOrFail();
            } else {
                $this->entity = $class::scope($publicId)->firstOrFail();
            }

        }
        catch(ModelNotFoundException $e) {

            if(Request::header('X-Ninja-Token') != '') {

                $error['error'] = ['message'=>trans('texts.client_not_found')];
                $error = json_encode($error, JSON_PRETTY_PRINT);
                $headers = Utils::getApiHeaders();

                return response()->make($error, 400, $headers);

            }
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
