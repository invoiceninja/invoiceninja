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

namespace App\Http\Requests\StripeConnect;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;

class InitializeStripeConnectRequest extends FormRequest
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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            //
        ];
    }

    /**
     * Resolve one-time token instance.
     *
     * @return mixed
     */
    public function getTokenContent()
    {
        $data = Cache::get($this->input('token'));

        abort_if(!$data, 404);

        return $data;
    }
}
