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

namespace App\Http\Requests\Twilio;

use App\Http\Requests\Request;
use App\Libraries\MultiDB;

class Confirm2faRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
    {
        return [
            'code' => 'required',
            'email' => 'required|exists:users,email',
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (array_key_exists('email', $input)) {
            MultiDB::userFindAndSetDb($input['email']);
        }

        $this->replace($input);
    }
}
