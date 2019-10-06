<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Requests\GroupSetting;

use App\Http\Requests\Request;
use App\Models\GroupSetting;
use Illuminate\Support\Facades\Log;

class StoreGroupSettingRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {

        return auth()->user()->can('create', GroupSetting::class);

    }

    public function rules()
    {

        return [
            'name' => 'required',
      //      'settings' => 'json',
        ];

    }


    public function sanitize()
    {
        $input = $this->all();

        $this->replace($input);   
    }

    public function messages()
    {
        return [
            'settings' => 'settings must be a valid json structure'
        ];
    }


}