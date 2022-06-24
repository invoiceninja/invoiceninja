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
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class UpdateRecurringInvoiceRequest extends Request
{
    use ChecksEntityStatus;
    use CleanLineItems;
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->recurring_invoice);
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

        if ($this->number) {
            $rules['number'] = Rule::unique('recurring_invoices')->where('company_id', auth()->user()->company()->id)->ignore($this->recurring_invoice->id);
        }

        $rules['project_id'] = ['bail', 'sometimes', new ValidProjectForClient($this->all())];

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

        if (isset($input['client_id'])) {
            $input['client_id'] = $this->decodePrimaryKey($input['client_id']);
        }

        if (array_key_exists('assigned_user_id', $input) && is_string($input['assigned_user_id'])) {
            $input['assigned_user_id'] = $this->decodePrimaryKey($input['assigned_user_id']);
        }

        if (array_key_exists('project_id', $input) && is_string($input['project_id'])) {
            $input['project_id'] = $this->decodePrimaryKey($input['project_id']);
        }

        if (isset($input['invitations'])) {
            foreach ($input['invitations'] as $key => $value) {
                if (is_numeric($input['invitations'][$key]['id'])) {
                    unset($input['invitations'][$key]['id']);
                }

                if (array_key_exists('id', $input['invitations'][$key]) && is_string($input['invitations'][$key]['id'])) {
                    $input['invitations'][$key]['id'] = $this->decodePrimaryKey($input['invitations'][$key]['id']);
                }

                if (is_string($input['invitations'][$key]['client_contact_id'])) {
                    $input['invitations'][$key]['client_contact_id'] = $this->decodePrimaryKey($input['invitations'][$key]['client_contact_id']);
                }
            }
        }

        if (isset($input['line_items'])) {
            $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];
        }

        if (array_key_exists('auto_bill', $input) && isset($input['auto_bill']) && $this->setAutoBillFlag($input['auto_bill'])) {
            $input['auto_bill_enabled'] = true;
        }

        if (array_key_exists('documents', $input)) {
            unset($input['documents']);
        }

        $this->replace($input);
    }

    /**
     * if($auto_bill == '')
     * off / optin / optout will reset the status of this field to off to allow
     * the client to choose whether to auto_bill or not.
     *
     * @param enum $auto_bill off/always/optin/optout
     *
     * @return bool
     */
    private function setAutoBillFlag($auto_bill) :bool
    {
        if ($auto_bill == 'always') {
            return true;
        }

        return false;
    }
}
