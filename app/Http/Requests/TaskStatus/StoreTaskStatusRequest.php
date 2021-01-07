<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Requests\TaskStatus;

use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;

class StoreTaskStatusRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->isAdmin();
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

            if(array_key_exists('color', $input) && is_null($input['color']))
                $input['color'] = '#fff';

        $this->replace($input);
    }

    public function rules()
    {
        $rules = [];

        $rules['name'] ='required|unique:task_statuses,name,null,null,company_id,'.auth()->user()->companyId();

        return $rules;
    }
}
