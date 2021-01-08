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

namespace App\Http\Requests\Project;

use App\Http\Requests\Request;
use App\Models\Client;
use App\Models\Project;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

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

        $rules['name'] = 'required';
        $rules['client_id'] = 'required|exists:clients,id,company_id,'.auth()->user()->company()->id;

        if (isset($this->number)) {
            $rules['number'] = Rule::unique('projects')->where('company_id', auth()->user()->company()->id);
        }

        return $this->globalRules($rules);
    }

    protected function prepareForValidation()
    {
        $input = $this->decodePrimaryKeys($this->all());


        if(array_key_exists('color', $input) && is_null($input['color']))
            $input['color'] = '#fff';
        
        $this->replace($input);
    }

    public function getClient($client_id)
    {
        return Client::find($client_id);
    }
}
