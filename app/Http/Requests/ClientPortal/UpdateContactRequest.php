<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Requests\ClientPortal;

use App\Http\Requests\Request;
use Zend\Diactoros\Response\JsonResponse;

class UpdateContactRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return true;
    }

    public function rules()
    {

        return [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email',
            'password' => 'sometimes|nullable|min:6|confirmed',
            'file' => 'sometimes|nullable|max:100000|mimes:png,svg,jpeg,gif,jpg,bmp'
        ];

    }

    

}

