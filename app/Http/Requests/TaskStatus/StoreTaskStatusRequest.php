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

    public function prepareForValidation()
    {
        $input = $this->all();

        if (array_key_exists('color', $input) && is_null($input['color'])) {
            $input['color'] = '';
        }

        $this->replace($input);
    }

    public function rules()
    {
        $rules = [];

        $rules['name'] = 'required|unique:task_statuses,name,null,null,company_id,'.auth()->user()->companyId();

        return $rules;
    }
}
