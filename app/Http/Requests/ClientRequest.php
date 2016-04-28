<?php namespace App\Http\Requests;

class ClientRequest extends BaseRequest {

    protected $entityType = ENTITY_CLIENT;

    public function authorize()
    {
        return true;
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
