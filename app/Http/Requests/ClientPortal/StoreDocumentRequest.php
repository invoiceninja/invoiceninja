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

namespace App\Http\Requests\ClientPortal;

use App\Http\Requests\Request;
use Zend\Diactoros\Response\JsonResponse;

class StoreDocumentRequest extends Request
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
            'file' => 'required|max:10000|mimes:png,svg,jpeg,gif,jpg,bmp',
        ];
    }

    public function response(array $errors)
    {
        return new JsonResponse(['error' => $errors], 400);
    }
}
