<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Shop;

use App\Http\Requests\Request;
use App\Http\ValidationRules\Invoice\UniqueInvoiceNumberRule;
use App\Models\Company;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;

class StoreShopInvoiceRequest extends Request
{
    use MakesHash;
    use CleanLineItems;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    private $company;

    public function authorize() : bool
    {
        return true;
    }

    public function rules()
    {
        $rules = [];

        if ($this->input('documents') && is_array($this->input('documents'))) {
            $documents = count($this->input('documents'));

            foreach (range(0, $documents) as $index) {
                $rules['documents.'.$index] = 'file|mimes:png,ai,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000';
            }
        } elseif ($this->input('documents')) {
            $rules['documents'] = 'file|mimes:png,ai,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000';
        }

        $rules['client_id'] = 'required|exists:clients,id,company_id,'.$this->company->id;

        $rules['invitations.*.client_contact_id'] = 'distinct';

        $rules['number'] = new UniqueInvoiceNumberRule($this->all());

        return $rules;
    }

    public function prepareForValidation()
    {
        $this->company = Company::where('company_key', request()->header('X-API-COMPANY-KEY'))->firstOrFail();

        $input = $this->all();

        if (array_key_exists('design_id', $input) && is_string($input['design_id'])) {
            $input['design_id'] = $this->decodePrimaryKey($input['design_id']);
        }

        if (array_key_exists('client_id', $input) && is_string($input['client_id'])) {
            $input['client_id'] = $this->decodePrimaryKey($input['client_id']);
        }

        if (array_key_exists('assigned_user_id', $input) && is_string($input['assigned_user_id'])) {
            $input['assigned_user_id'] = $this->decodePrimaryKey($input['assigned_user_id']);
        }

        if (isset($input['client_contacts'])) {
            foreach ($input['client_contacts'] as $key => $contact) {
                if (! array_key_exists('send_email', $contact) || ! array_key_exists('id', $contact)) {
                    unset($input['client_contacts'][$key]);
                }
            }
        }

        if (isset($input['invitations'])) {
            foreach ($input['invitations'] as $key => $value) {
                if (isset($input['invitations'][$key]['id']) && is_numeric($input['invitations'][$key]['id'])) {
                    unset($input['invitations'][$key]['id']);
                }

                if (isset($input['invitations'][$key]['id']) && is_string($input['invitations'][$key]['id'])) {
                    $input['invitations'][$key]['id'] = $this->decodePrimaryKey($input['invitations'][$key]['id']);
                }

                if (is_string($input['invitations'][$key]['client_contact_id'])) {
                    $input['invitations'][$key]['client_contact_id'] = $this->decodePrimaryKey($input['invitations'][$key]['client_contact_id']);
                }
            }
        }

        $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];
        //$input['line_items'] = json_encode($input['line_items']);
        $this->replace($input);
    }
}
