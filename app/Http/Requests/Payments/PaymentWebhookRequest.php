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


namespace App\Http\Requests\Payments;

use App\Models\Company;
use App\Models\CompanyGateway;
use App\Http\Requests\Request;

class PaymentWebhookRequest extends Request
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            //
        ];
    }

    public function company()
    {
        if (! $this->company_key) {
            return false;
        }

        return Company::query()
            ->where('company_key', $this->company_key)
            ->firstOrFail();
    }

    public function companyGateway()
    {
        if (! $this->gateway_key || ! $this->company_key) {
            return false;
        }

        $company = $this->company();

        return CompanyGateway::query()
            ->where('gateway_key', $this->gateway_key)
            ->where('company_id', $company->id)
            ->firstOrFail();
    }
}
