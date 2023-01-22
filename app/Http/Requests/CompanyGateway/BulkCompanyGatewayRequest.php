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

namespace App\Http\Requests\CompanyGateway;

use App\Http\Requests\Request;
use App\Http\ValidationRules\ValidCompanyGatewayFeesAndLimitsRule;
use App\Models\Gateway;
use App\Utils\Traits\CompanyGatewayFeesAndLimitsSaver;
use Illuminate\Validation\Rule;

class BulkCompanyGatewayRequest extends Request

{

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
            'ids' => 'required|bail|array',
            'action' => 'in:archive,restore,delete'
        ];

    }


}
