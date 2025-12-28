<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\ClientPortal\Credits;

use App\Http\ViewComposers\PortalComposer;
use Illuminate\Foundation\Http\FormRequest;

class ShowCreditsRequest extends FormRequest
{
    public function authorize()
    {
        return (bool)(auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_CREDITS);
    }

    public function rules()
    {
        return [
            //
        ];
    }
}
