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

namespace App\Http\Requests\Design;

use App\Http\Requests\Request;
use App\Models\Design;
use App\Utils\Traits\ChecksEntityStatus;
use Illuminate\Support\Facades\Log;

class UpdateDesignRequest extends Request
{
    use ChecksEntityStatus;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return auth()->user()->isAdmin();
    }

    public function rules()
    {
        return [
        //    'name' => 'unique:designs,name,'.$this->designs->name.',id,company_id,'.auth()->user()->companyId(),
        ];
    }
}
