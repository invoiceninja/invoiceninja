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

namespace App\Http\Requests\RecurringInvoice;

use App\Http\Requests\Request;
use App\Http\ValidationRules\Project\ValidProjectForClient;
use App\Http\ValidationRules\Recurring\UniqueRecurringInvoiceNumberRule;
use App\Models\Client;
use App\Models\RecurringInvoice;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\UploadedFile;

class StoreRecurringInvoiceRequest extends Request
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
        return auth()->user()->can('create', RecurringInvoice::class);
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

        $rules['client_id'] = 'required|exists:clients,id,company_id,'.auth()->user()->company()->id;

        $rules['invitations.*.client_contact_id'] = 'distinct';

        $rules['frequency_id'] = 'required|integer|digits_between:1,12';

        $rules['project_id'] = ['bail', 'sometimes', new ValidProjectForClient($this->all())];

        $rules['number'] = new UniqueRecurringInvoiceNumberRule($this->all());

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (array_key_exists('next_send_date', $input) && is_string($input['next_send_date'])) {
            $input['next_send_date_client'] = $input['next_send_date'];
        }

        if (array_key_exists('design_id', $input) && is_string($input['design_id'])) {
            $input['design_id'] = $this->decodePrimaryKey($input['design_id']);
        }

        if (array_key_exists('client_id', $input) && is_string($input['client_id'])) {
            $input['client_id'] = $this->decodePrimaryKey($input['client_id']);
        }

        if (array_key_exists('assigned_user_id', $input) && is_string($input['assigned_user_id'])) {
            $input['assigned_user_id'] = $this->decodePrimaryKey($input['assigned_user_id']);
        }

        if (array_key_exists('vendor_id', $input) && is_string($input['vendor_id'])) {
            $input['vendor_id'] = $this->decodePrimaryKey($input['vendor_id']);
        }

        if (array_key_exists('project_id', $input) && is_string($input['project_id'])) {
            $input['project_id'] = $this->decodePrimaryKey($input['project_id']);
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

        if (isset($input['auto_bill'])) {
            $input['auto_bill_enabled'] = $this->setAutoBillFlag($input['auto_bill']);
        } else {
            if (array_key_exists('client_id', $input) && $client = Client::find($input['client_id'])) {
                $input['auto_bill'] = $client->getSetting('auto_bill');
                $input['auto_bill_enabled'] = $this->setAutoBillFlag($input['auto_bill']);
            }
        }

        /* If there is no number, just unset it here. */
        if (array_key_exists('number', $input) && (is_null($input['number']) || empty($input['number']))) {
            unset($input['number']);
        }

        $this->replace($input);
    }

    private function setAutoBillFlag($auto_bill)
    {
        if ($auto_bill == 'always' || $auto_bill == 'optout') {
            return true;
        }

        return false;
    }

    public function messages()
    {
        return [];
    }
}
