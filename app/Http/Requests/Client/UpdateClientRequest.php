<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Requests\Client;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Http\Requests\Request;
use App\Http\ValidationRules\IsDeletedRule;
use App\Http\ValidationRules\ValidClientGroupSettingsRule;
use App\Http\ValidationRules\ValidSettingsRule;
use App\Utils\Ninja;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends Request
{
    use MakesHash;
    use ChecksEntityStatus;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->client);
    }

    public function rules()
    {
        /* Ensure we have a client name, and that all emails are unique*/

        if ($this->input('documents') && is_array($this->input('documents'))) {
            $documents = count($this->input('documents'));

            foreach (range(0, $documents) as $index) {
                $rules['documents.'.$index] = 'file|mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000';
            }
        } elseif ($this->input('documents')) {
            $rules['documents'] = 'file|mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000';
        }

        $rules['company_logo'] = 'mimes:jpeg,jpg,png,gif|max:10000';
        $rules['industry_id'] = 'integer|nullable';
        $rules['size_id'] = 'integer|nullable';
        $rules['country_id'] = 'integer|nullable';
        $rules['shipping_country_id'] = 'integer|nullable';
        //$rules['id_number'] = 'unique:clients,id_number,,id,company_id,' . auth()->user()->company()->id;
        $rules['id_number'] = 'unique:clients,id_number,'.$this->id.',id,company_id,'.$this->company_id;
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

        return $rules;
    }

    public function messages()
    {
        return [
            'unique' => ctrans('validation.unique', ['attribute' => 'email']),
            'email' => ctrans('validation.email', ['attribute' => 'email']),
            'name.required' => ctrans('validation.required', ['attribute' => 'name']),
            'required' => ctrans('validation.required', ['attribute' => 'email']),
        ];
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['group_settings_id'])) {
            $input['group_settings_id'] = $this->decodePrimaryKey($input['group_settings_id']);
        }

        if (array_key_exists('assigned_user_id', $input) && is_string($input['assigned_user_id'])) {
            $input['assigned_user_id'] = $this->decodePrimaryKey($input['assigned_user_id']);
        }

        if (isset($input['contacts'])) {
            foreach ($input['contacts'] as $key => $contact) {
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
            }
        }

        if (array_key_exists('settings', $input)) {
            $input['settings'] = $this->filterSaveableSettings($input['settings']);
        }

        $this->replace($input);
    }

    /**
     * For the hosted platform, we restrict the feature settings.
     *
     * This method will trim the company settings object
     * down to the free plan setting properties which
     * are saveable
     *
     * @param  object $settings
     * @return object $settings
     */
    private function filterSaveableSettings($settings)
    {
        $account = $this->client->company->account;

        if (! $account->isFreeHostedClient()) {
            return $settings;
        }

        $saveable_casts = CompanySettings::$free_plan_casts;

        foreach ($settings as $key => $value) {
            if (! array_key_exists($key, $saveable_casts)) {
                unset($settings->{$key});
            }
        }

        return $settings;
    }
}
