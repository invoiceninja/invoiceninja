<?php

namespace App\Http\Requests\Payments;

use App\Models\Company;
use App\Models\CompanyGateway;
use Illuminate\Foundation\Http\FormRequest;

class PaymentWebhookRequest extends FormRequest
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
