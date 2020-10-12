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

namespace App\Http\Requests\Project;

use App\Http\Requests\Request;
use App\Models\Project;
use App\Utils\Traits\MakesHash;

class StoreProjectRequest extends Request
{
    use MakesHash;
    
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('create', Project::class);
    }

    public function rules()
    {
        $rules = [];

            //$rules['name'] ='required|unique:projects,name,null,null,company_id,'.auth()->user()->companyId();
            $rules['client_id'] = 'required|exists:clients,id,company_id,'.auth()->user()->company()->id;

        return $rules;
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        if (array_key_exists('client_id', $input) && is_string($input['client_id'])) {
            $input['client_id'] = $this->decodePrimaryKey($input['client_id']);
        }

        
        $this->replace($input);
    }
}
