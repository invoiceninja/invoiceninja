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

namespace App\Http\Requests\Invoice;

use App\Http\Requests\Request;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UpdateCompanyUserRequest extends Request
{
    use MakesHash;
    
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return auth()->user()->isAdmin() || (auth()->user()->id == $this->user->id);
    }


    public function rules()
    {
        return [];
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        $modified = [];

        if(auth()->user()->isAdmin())
          $modified = $input;
        else{
          $modified['company_user']['settings'] = $input['company_user']['settings'];
          $modified['company_id'] = $this->decodePrimaryKey($input['company_id']);
          $modified['user_id'] = $this->decodePrimaryKey($input['user_id']);
        }

        $this->replace($modified);
    }
}
