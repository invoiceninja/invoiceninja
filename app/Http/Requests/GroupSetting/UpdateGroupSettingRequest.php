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
use App\Http\ValidationRules\ValidClientGroupSettingsRule;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UpdateGroupSettingRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->group_setting);
    }

    public function rules()
    {
        $this->sanitize();

        $rules['settings'] = new ValidClientGroupSettingsRule();
        
        return $rules;

    }

    public function sanitize()
    {
        $input = $this->all();

        $this->replace($input);   

        return $this->all();

    }



}