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

namespace App\Http\Requests\Setup;

use App\Http\Requests\Request;

class StoreSetupRequest extends Request
{

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return true;
    }

    public function rules()
    {
        return [
          /*System*/
          'url' => 'required',
          /*Database*/
          'host' => 'required',
          'database' => 'required',
          'db_username' => 'required',
          'db_password' => '',
          /*Mail driver*/
          'mail_driver' => 'required',
          'encryption' => 'required',
          'mail_host' => 'required',
          'mail_username' => 'required',
          'mail_name' => 'required',
          'mail_address' => 'required',
          'mail_password' => 'required',
          /*user registration*/
          'privacy_policy'    => 'required',
          'terms_of_service'  => 'required',
          'first_name' => 'required',
          'last_name' => 'required',
          'email' => 'required',
          'password' => 'required'
        ];
    }

    protected function prepareForValidation()
    {
        $input = $this->all();
          
        $input['user_agent'] = request()->server('HTTP_USER_AGENT');

        $this->replace($input);
    }
}
