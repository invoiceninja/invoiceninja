<?php namespace App\Http\Requests;

class CreateDocumentRequest extends DocumentRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->can('create', ENTITY_DOCUMENT) && $this->user()->hasFeature(FEATURE_DOCUMENTS);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            //'file' => 'mimes:jpg'
        ];
    }

    /**
     * Sanitize input before validation.
     *
     * @return array
     */
     /*
    public function sanitize()
    {
        $input = $this->all();

        $input['phone'] = 'test123';

        $this->replace($input);

        return $this->all();
    }
    */
}
