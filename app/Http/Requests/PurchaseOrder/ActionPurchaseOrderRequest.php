<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\PurchaseOrder;

use App\Http\Requests\Request;
use App\Models\PurchaseOrder;
use App\Utils\Traits\MakesHash;

class ActionPurchaseOrderRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    private $error_msg;

    // private $invoice;

    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->purchase_order);
    }

    public function rules()
    {
        return [
            'action' => 'required',
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if ($this->action) {
            $input['action'] = $this->action;
        } elseif (! array_key_exists('action', $input)) {
            $this->error_msg = 'Action is a required field';
        }

        $this->replace($input);
    }

    public function messages()
    {
        return [
            'action' => $this->error_msg,
        ];
    }
}
