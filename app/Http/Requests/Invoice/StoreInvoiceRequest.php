<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Requests\Invoice;

use App\Http\Requests\Request;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;

class StoreInvoiceRequest extends Request
{
    use MakesHash;
    use CleanLineItems;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return auth()->user()->can('create', Invoice::class);
    }

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

        if(array_key_exists('design_id', $input) && is_string($input['design_id']))
          $input['design_id'] = $this->decodePrimaryKey($input['design_id']);

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
