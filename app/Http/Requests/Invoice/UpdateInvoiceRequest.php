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
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends Request
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
        return auth()->user()->can('edit', $this->invoice);
    }


    public function rules()
    {
        $rules = [];

        if ($this->input('documents') && is_array($this->input('documents'))) {
            $documents = count($this->input('documents'));

            foreach (range(0, $documents) as $index) {
                $rules['documents.' . $index] = 'file|mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000';
            }
        } elseif ($this->input('documents')) {
            $rules['documents'] = 'file|mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000';
        }

        return $rules;
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        if (array_key_exists('design_id', $input) && is_string($input['design_id'])) {
            $input['design_id'] = $this->decodePrimaryKey($input['design_id']);
        }
        
        if (isset($input['client_id'])) {
            $input['client_id'] = $this->decodePrimaryKey($input['client_id']);
        }

        if (isset($input['invitations'])) {
            foreach ($input['invitations'] as $key => $value) {
                if (is_numeric($input['invitations'][$key]['id'])) {
                    unset($input['invitations'][$key]['id']);
                }

                if (is_string($input['invitations'][$key]['id'])) {
                    $input['invitations'][$key]['id'] = $this->decodePrimaryKey($input['invitations'][$key]['id']);
                }

                if (is_string($input['invitations'][$key]['client_contact_id'])) {
                    $input['invitations'][$key]['client_contact_id'] = $this->decodePrimaryKey($input['invitations'][$key]['client_contact_id']);
                }
            }
        }
        
        $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];

        $this->replace($input);
    }
}
