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

namespace App\Http\Requests\Client;

use App\DataMapper\ClientSettings;
use App\Http\Requests\Request;
use App\Http\ValidationRules\ValidClientGroupSettingsRule;
use App\Models\Client;
use App\Models\GroupSetting;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class StoreClientRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return auth()->user()->can('create', Client::class);
    }

    public function rules()
    {

        /* Ensure we have a client name, and that all emails are unique*/
        //$rules['name'] = 'required|min:1';
        $rules['id_number'] = 'unique:clients,id_number,' . $this->id . ',id,company_id,' . $this->company_id;
        $rules['settings'] = new ValidClientGroupSettingsRule();
        $rules['contacts.*.email'] = 'nullable|distinct';
        $rules['contacts.*.password'] = [
                                        'nullable',
                                        'sometimes',
                                        'string',
                                        'min:7',             // must be at least 10 characters in length
                                        'regex:/[a-z]/',      // must contain at least one lowercase letter
                                        'regex:/[A-Z]/',      // must contain at least one uppercase letter
                                        'regex:/[0-9]/',      // must contain at least one digit
                                        //'regex:/[@$!%*#?&.]/', // must contain a special character
                                        ];


//        $contacts = request('contacts');

        // if (is_array($contacts)) {
        //     for ($i = 0; $i < count($contacts); $i++) {

        //         //$rules['contacts.' . $i . '.email'] = 'nullable|email|distinct';
        //     }
        // }

        return $rules;
    }


    protected function prepareForValidation()
    {

        $input = $this->all();

        //@todo implement feature permissions for > 100 clients
        if (!isset($input['settings'])) {
            $input['settings'] = ClientSettings::defaults();
        }
        
        if (isset($input['group_settings_id'])) {
            $input['group_settings_id'] = $this->decodePrimaryKey($input['group_settings_id']);
        }

        if(empty($input['settings']->currency_id))
        {
            if(empty($input['group_settings_id']))
            {
                $input['settings']->currency_id = auth()->user()->company()->settings->currency_id;
            }
            else
            {
                $group_settings = GroupSetting::find($input['group_settings_id']);

                if($group_settings && property_exists($group_settings, 'currency_id') && is_int($group_settings->currency_id))
                    $input['settings']->currency_id = $group_settings->currency_id;
                else
                  $input['settings']->currency_id = auth()->user()->company()->settings->currency_id;
            }
        }

        if(isset($input['contacts']))
        {
            foreach($input['contacts'] as $key => $contact)
            {
                if(array_key_exists('id', $contact) && is_numeric($contact['id']))
                    unset($input['contacts'][$key]['id']);
                elseif(array_key_exists('id', $contact) && is_string($contact['id']))
                    $input['contacts'][$key]['id'] = $this->decodePrimaryKey($contact['id']);


                //Filter the client contact password - if it is sent with ***** we should ignore it!
                if(isset($contact['password']))
                {

                    if(strlen($contact['password']) == 0){
                        $input['contacts'][$key]['password'] = '';
                    }
                    else {
                        $contact['password'] = str_replace("*", "", $contact['password']);

                        if(strlen($contact['password']) == 0){
                            unset($input['contacts'][$key]['password']);
                        }

                    }

                }

            }
        }

        $this->replace($input);
    }

    public function messages()
    {
        return [
            'unique' => ctrans('validation.unique', ['attribute' => 'email']),
            //'required' => trans('validation.required', ['attribute' => 'email']),
            'contacts.*.email.required' => ctrans('validation.email', ['attribute' => 'email']),
        ];
    }
}
