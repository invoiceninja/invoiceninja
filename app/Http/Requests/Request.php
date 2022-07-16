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

namespace App\Http\Requests;

use App\Http\Requests\RuntimeFormRequest;
use App\Http\ValidationRules\User\RelatedUserRule;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Http\FormRequest;

class Request extends FormRequest
{
    use MakesHash;
    use RuntimeFormRequest;
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }

    public function globalRules($rules)
    {
        $merge_rules = [];

        foreach ($this->all() as $key => $value) {
            if (method_exists($this, $key)) {
                $merge_rules = $this->{$key}($rules);
            }
        }

        //01-02-2022 needed for CSV Imports
        if(!$merge_rules)
            return $rules;

        return array_merge($merge_rules, $rules);
    }

    private function assigned_user_id($rules)
    {
        $rules['assigned_user_id'] = [
            'bail' ,
            'sometimes',
            'nullable',
                new RelatedUserRule($this->all())
            ];

        return $rules;
    }

    private function invoice_id($rules)
    {
        $rules['invoice_id'] = 'bail|nullable|sometimes|exists:invoices,id,company_id,'.auth()->user()->company()->id.',client_id,'.$this['client_id'];

        return $rules;
    }

    private function vendor_id($rules)
    {
        $rules['vendor_id'] = 'bail|nullable|sometimes|exists:vendors,id,company_id,'.auth()->user()->company()->id;

        return $rules;
    }

    public function decodePrimaryKeys($input)
    {
        if (array_key_exists('group_id', $input) && is_string($input['group_id'])) {
            $input['group_id'] = $this->decodePrimaryKey($input['group_id']);
        }

        if (array_key_exists('subscription_id', $input) && is_string($input['subscription_id'])) {
            $input['subscription_id'] = $this->decodePrimaryKey($input['subscription_id']);
        }

        if (array_key_exists('assigned_user_id', $input) && is_string($input['assigned_user_id'])) {
            $input['assigned_user_id'] = $this->decodePrimaryKey($input['assigned_user_id']);
        }

        if (array_key_exists('user_id', $input) && is_string($input['user_id'])) {
            $input['user_id'] = $this->decodePrimaryKey($input['user_id']);
        }

        if (array_key_exists('vendor_id', $input) && is_string($input['vendor_id'])) {
            $input['vendor_id'] = $this->decodePrimaryKey($input['vendor_id']);
        }

        if (array_key_exists('client_id', $input) && is_string($input['client_id'])) {
            $input['client_id'] = $this->decodePrimaryKey($input['client_id']);
        }

        if (array_key_exists('invoice_id', $input) && is_string($input['invoice_id'])) {
            $input['invoice_id'] = $this->decodePrimaryKey($input['invoice_id']);
        }

        if (array_key_exists('design_id', $input) && is_string($input['design_id'])) {
            $input['design_id'] = $this->decodePrimaryKey($input['design_id']);
        }

        if (array_key_exists('project_id', $input) && is_string($input['project_id'])) {
            $input['project_id'] = $this->decodePrimaryKey($input['project_id']);
        }

        if (array_key_exists('company_gateway_id', $input) && is_string($input['company_gateway_id'])) {
            $input['company_gateway_id'] = $this->decodePrimaryKey($input['company_gateway_id']);
        }

        if (isset($input['client_contacts'])) {
            foreach ($input['client_contacts'] as $key => $contact) {
                if (! array_key_exists('send_email', $contact) || ! array_key_exists('id', $contact)) {
                    unset($input['client_contacts'][$key]);
                }
            }
        }

        if (isset($input['invitations']) && is_array($input['invitations'])) {
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

        if (isset($input['contacts']) && is_array($input['contacts'])) {
            foreach ($input['contacts'] as $key => $contact) {

                if(!is_array($contact))
                    continue;

                if (array_key_exists('id', $contact) && is_numeric($contact['id'])) {
                    unset($input['contacts'][$key]['id']);
                } elseif (array_key_exists('id', $contact) && is_string($contact['id'])) {
                    $input['contacts'][$key]['id'] = $this->decodePrimaryKey($contact['id']);
                }

                //Filter the client contact password - if it is sent with ***** we should ignore it!
                if (isset($contact['password'])) {
                    if (strlen($contact['password']) == 0) {
                        $input['contacts'][$key]['password'] = '';
                    } else {
                        $contact['password'] = str_replace('*', '', $contact['password']);

                        if (strlen($contact['password']) == 0) {
                            unset($input['contacts'][$key]['password']);
                        }
                    }
                }

                if (array_key_exists('email', $contact)) 
                    $input['contacts'][$key]['email'] = trim($contact['email']);


            }
        }
        
        return $input;
    }

    protected function prepareForValidation()
    {

    }
}
