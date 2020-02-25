<?php

namespace App\Http\Requests\Credit;

use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCreditRequest extends FormRequest
{
    use MakesHash;
    use CleanLineItems;
    use ChecksEntityStatus;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->credit);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'documents' => 'mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx',
            //'client_id' => 'required|integer',
            //'invoice_type_id' => 'integer',
        ];
    }


    protected function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['client_id'])) {
            $input['client_id'] = $this->decodePrimaryKey($input['client_id']);
        }

        if(isset($input['invitations']))
        {

          foreach($input['invitations'] as $key => $value)
          {

            if(is_numeric($input['invitations'][$key]['id']))
              unset($input['invitations'][$key]['id']);

            if(is_string($input['invitations'][$key]['id']))
              $input['invitations'][$key]['id'] = $this->decodePrimaryKey($input['invitations'][$key]['id']);

            if(is_string($input['invitations'][$key]['client_contact_id']))
              $input['invitations'][$key]['client_contact_id'] = $this->decodePrimaryKey($input['invitations'][$key]['client_contact_id']);

          }

        }
        
        $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];

        $this->replace($input);
    }
}
