<?php

namespace App\Http\Requests\Gateways\Checkout3ds;

use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\PaymentHash;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Http\FormRequest;

class Checkout3dsRequest extends FormRequest
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
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

    public function getCompany()
    {
        MultiDB::findAndSetDbByCompanyKey($this->company_key);

        return Company::where('company_key', $this->company_key)->first();
    }

    public function getCompanyGateway()
    {
        MultiDB::findAndSetDbByCompanyKey($this->company_key);

        return CompanyGateway::find($this->decodePrimaryKey($this->company_gateway_id));
    }

    public function getPaymentHash()
    {
        MultiDB::findAndSetDbByCompanyKey($this->company_key);

        return PaymentHash::where('hash', $this->hash)->first();
    }

    public function getClient()
    {
        MultiDB::findAndSetDbByCompanyKey($this->company_key);

        return Client::withTrashed()->find($this->getPaymentHash()->data->client_id);
    }
}
