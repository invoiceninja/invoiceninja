<?php

namespace App\Http\Requests\ClientPortal\RecurringInvoices;

use App\Http\ViewComposers\PortalComposer;
use Illuminate\Foundation\Http\FormRequest;

class RequestCancellationRequest extends FormRequest
{
    public function authorize()
    {

        auth()->guard('contact')->user()->loadMissing(['company']);

        return (bool)(auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_RECURRING_INVOICES);
    }

    public function rules()
    {
        return [
            //
        ];
    }
}
