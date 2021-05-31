<?php

namespace App\Http\Requests\Gateways\Checkout3ds;

use App\Models\Client;
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

    public function getCompanyGateway()
    {
        return CompanyGateway::findOrFail($this->decodePrimaryKey($this->company_gateway_id));
    }

    public function getPaymentHash()
    {
        return PaymentHash::where('hash', $this->hash)->firstOrFail();
    }

    public function getClient()
    {
        return Client::findOrFail($this->getPaymentHash()->data->client_id);
    }
}
