<?php

namespace App\Http\Requests\Credit;

use App\Models\Credit;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Http\FormRequest;

class StoreCreditRequest extends FormRequest
{
    use MakesHash;
    use CleanLineItems;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('create', Credit::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'client_id' => 'required|exists:clients,id',
           // 'invoice_type_id' => 'integer',
      //      'documents' => 'mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx',
        ];
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        if($input['client_id'])
          $input['client_id'] = $this->decodePrimaryKey($input['client_id']);

        if(isset($input['client_contacts']))
        {
          foreach($input['client_contacts'] as $key => $contact)
          {
            if(!array_key_exists('send_email', $contact) || !array_key_exists('id', $contact))
            {
              unset($input['client_contacts'][$key]);
            }
          }

        }

        if(isset($input['invitations']))
        {

          foreach($input['invitations'] as $key => $value)
          {

            if(isset($input['invitations'][$key]['id']) && is_numeric($input['invitations'][$key]['id']))
              unset($input['invitations'][$key]['id']);

            if(isset($input['invitations'][$key]['id']) && is_string($input['invitations'][$key]['id']))
              $input['invitations'][$key]['id'] = $this->decodePrimaryKey($input['invitations'][$key]['id']);

            if(is_string($input['invitations'][$key]['client_contact_id']))
              $input['invitations'][$key]['client_contact_id'] = $this->decodePrimaryKey($input['invitations'][$key]['client_contact_id']);

          }

        }

        $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];
        //$input['line_items'] = json_encode($input['line_items']);
        $this->replace($input);
    }
}
