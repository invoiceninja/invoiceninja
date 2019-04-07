<?php

namespace App\Ninja\Import\CSV;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

/**
 * Class PaymentTransformer.
 */
class PaymentTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return Item
     */
    public function transform($data)
    {
        return new Item($data, function ($data) {
            return [
				'invoice_id' => $data->invoice_id,
				//'account_id
				'client_id' => $data->client_id,
				//'contact_id'
				//'invitation_id'
				//'user_id'
				//'account_gateway_id'
				//'payment_type_id'
				'created_at' => isset($data->created_at) ? date('Y-m-d', strtotime($data->created_at)) : null,
				'updated_at' => isset($data->updated_at) ? date('Y-m-d', strtotime($data->updated_at)) : null,
				'deleted_at' => isset($data->deleted_at) ? date('Y-m-d', strtotime($data->deleted_at)) : null,
				'is_deleted' => $clientId ? false : true,
                'amount' => $this->getFloat($data, 'paid'),
                'payment_date_sql' => isset($data->invoice_date) ? $data->invoice_date : null,
				'transaction_reference' => $this->getString($data, 'transaction_reference'),
				//'payer_id'
				//'public_id'
				'refunded ' => getFloat($data, 'refunded'),
				//payment_status_id
				'routing_number' => getNumber($data, 'routing_number'),
				//'last4'
				'expiration' => isset($data->expiration) ? date('Y-m-d', strtotime($data->expiration)) : null,
				'gateway_error' => $this->getString($data, 'gateway_error'),
				'email' => $this->getString($data, 'payment_email'),
				//'payment_method_id'
                //'bank_name' => $this->getString($data, 'bank_name')
				//'ip' = 
				//'credit_ids'
				'private_notes' => $this->getString($data, 'private_notes'),
				'exchange_rate' => $this->getNumber($data, 'exchange_rate'),
				//'expense_currency_id' => isset($data->expense_currency) ? $this->getExpenseCurrencyId($data->expense_currency) : null,
                
            ];
        });
    }
}
