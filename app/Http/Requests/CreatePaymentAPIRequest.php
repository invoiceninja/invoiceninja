<?php namespace App\Http\Requests;

use App\Libraries\Utils;
use App\Models\Invoice;
use Illuminate\Http\Request as InputRequest;
use Response;


class CreatePaymentAPIRequest extends PaymentRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function __construct(InputRequest $req)
    {
        $this->req = $req;
    }

    public function authorize()
    {
        return $this->user()->can('create', ENTITY_PAYMENT);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        if ( ! $this->invoice_id || ! $this->amount) {
            return [
                'invoice_id' => 'required|numeric|min:1',
                'amount' => 'required|numeric|min:0.01',
            ];
        }

        $invoice = Invoice::scope($this->invoice_id)
            ->invoices()
            ->whereIsPublic(true)
            ->firstOrFail();

        $this->merge([
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client->id,
        ]);

        $rules = [
            'amount' => "required|numeric|between:0.01,{$invoice->balance}",
        ];

        if ($this->payment_type_id == PAYMENT_TYPE_CREDIT) {
            $rules['payment_type_id'] = 'has_credit:' . $invoice->client->public_id . ',' . $this->amount;
        }

        return $rules;
    }


    public function response(array $errors)
    {
        /* If the user is not validating from a mobile app - pass through parent::response */
        if(!isset($this->req->api_secret))
            return parent::response($errors);

        /* If the user is validating from a mobile app - pass through first error string and return error */
        foreach($errors as $error) {
            foreach ($error as $key => $value) {

                $message['error'] = ['message'=>$value];
                $message = json_encode($message, JSON_PRETTY_PRINT);
                $headers = Utils::getApiHeaders();

                return Response::make($message, 400, $headers);
            }
        }
    }
}
