<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Preview;

use App\Http\Requests\Request;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;

class PreviewPurchaseOrderRequest extends Request
{
    use MakesHash;
    use CleanLineItems;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->hasIntersectPermissionsOrAdmin(['create_purchase_order', 'edit_purchase_order', 'view_purchase_order']);
    }

    public function rules()
    {
        $rules = [];

        $rules['number'] = ['nullable'];

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];
        $input['amount'] = 0;
        $input['balance'] = 0;
        $input['number'] = ctrans('texts.live_preview') . " #". rand(0, 1000);
        
        $this->replace($input);
    }
}
