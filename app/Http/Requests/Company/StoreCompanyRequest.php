<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Company;

use App\Http\Requests\Request;
use App\Http\ValidationRules\Company\ValidCompanyQuantity;
use App\Http\ValidationRules\Company\ValidExpenseMailbox;
use App\Http\ValidationRules\Company\ValidSubdomain;
use App\Http\ValidationRules\ValidSettingsRule;
use App\Models\Company;
use App\Utils\Ninja;
use App\Libraries\MultiDB;
use App\Utils\Traits\MakesHash;

class StoreCompanyRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User auth()->user */
        $user = auth()->user();
        return $user->can('create', Company::class);
    }

    public function rules()
    {
        $input = $this->all();

        $rules = [];

        $rules['name'] = new ValidCompanyQuantity();
        $rules['company_logo'] = 'mimes:jpeg,jpg,png,gif|max:10000'; // max 10000kb
        $rules['settings'] = new ValidSettingsRule();

        if (isset($input['portal_mode']) && ($input['portal_mode'] == 'domain' || $input['portal_mode'] == 'iframe')) {
            $rules['portal_domain'] = 'sometimes|url';
        } else {
            if (Ninja::isHosted()) {
                $rules['subdomain'] = ['nullable', 'regex:/^[a-zA-Z0-9-]{1,63}$/', new ValidSubdomain()];
            } else {
                $rules['subdomain'] = 'nullable|alpha_num';
            }
        }

        $rules['expense_mailbox'] = new ValidExpenseMailbox();

        $rules['smtp_host'] = 'sometimes|string|nullable';
        $rules['smtp_port'] = 'sometimes|integer|nullable';
        $rules['smtp_encryption'] = 'sometimes|string';
        $rules['smtp_local_domain'] = 'sometimes|string|nullable';
        $rules['smtp_encryption'] = 'sometimes|string|nullable';
        $rules['smtp_local_domain'] = 'sometimes|string|nullable';

        // $rules['smtp_verify_peer'] = 'sometimes|in:true,false';

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (!isset($input['name'])) {
            $input['name'] = 'Untitled Company';
        }

        if (isset($input['google_analytics_url'])) {
            $input['google_analytics_key'] = $input['google_analytics_url'];
        }

        if (isset($input['portal_domain'])) {
            $input['portal_domain'] = rtrim(strtolower($input['portal_domain']), "/");
        }

        if (isset($input['expense_mailbox']) && Ninja::isHosted() && !($this->company->account->isPaid() && $this->company->account->plan == 'enterprise')) {
            unset($input['expense_mailbox']);
        }

        if (Ninja::isHosted() && !isset($input['subdomain'])) {
            $input['subdomain'] = MultiDB::randomSubdomainGenerator();
        }

        if (isset($input['smtp_username']) && strlen(str_replace("*", "", $input['smtp_username'])) < 2) {
            unset($input['smtp_username']);
        }

        if (isset($input['smtp_password']) && strlen(str_replace("*", "", $input['smtp_password'])) < 2) {
            unset($input['smtp_password']);
        }

        if (isset($input['smtp_port'])) {
            $input['smtp_port'] = (int) $input['smtp_port'];
        }

        if (isset($input['smtp_verify_peer']) && is_string($input['smtp_verify_peer'])) {
            $input['smtp_verify_peer'] == 'true' ? true : false;
        }

        $this->replace($input);
    }
}
