<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\VendorPortal;

use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;

class UpdateVendorContactRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->encodePrimaryKey(auth()->guard('vendor')->user()->id) === request()->segment(3);
    }

    public function rules()
    {
        return [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email:rfc,dns|unique:vendor_contacts,email,' . auth()->guard('vendor')->user()->id,
            'phone' => 'sometimes|nullable',
        ];
    }
}
