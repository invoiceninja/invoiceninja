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
namespace App\Http\Requests\ClientPortal\Credits;

use App\Http\ViewComposers\PortalComposer;
use Illuminate\Foundation\Http\FormRequest;

class ShowCreditRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        auth()->guard('contact')->user()->loadMissing(['company']);

        return ! $this->credit->is_deleted
           && (bool) (auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_CREDITS)
           && auth()->guard('contact')->user()->client_id === $this->credit->client_id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            //
        ];
    }
}
