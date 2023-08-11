<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Yodlee;

use App\Http\Requests\Request;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * @class \App\Http\Requests\Yodlee\YodleeAuthRequest
 * @property string $token
 * @property string $state
 */
class YodleeAuthRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }

    public function getTokenContent()
    {
        if ($this->state) {
            $this->token = $this->state;
        }

        $data = Cache::get($this->token);

        return $data;
    }

    public function getContact()
    {
        MultiDB::findAndSetDbByCompanyKey($this->getTokenContent()['company_key']);

        return User::findOrFail($this->getTokenContent()['user_id']);
    }

    public function getCompany()
    {
        MultiDB::findAndSetDbByCompanyKey($this->getTokenContent()['company_key']);

        return Company::where('company_key', $this->getTokenContent()['company_key'])->firstOrFail();
    }
}
