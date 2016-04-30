<?php namespace App\Http\Requests;

class ClientRequest extends BaseRequest {

    protected $entityType = ENTITY_CLIENT;

    public function entity()
    {
        return parent::entity()->load('contacts');
    }

    public function authorize()
    {
        return $this->user()->can('view', $this->entity());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }
}
