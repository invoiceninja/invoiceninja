<?php

namespace App\Ninja\Import\Stripe;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;
use App\Models\PaymentType;

/**
 * Class InvoiceTransformer.
 */
class CustomerTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return bool|Item
     */
    public function transform($data)
    {
        if (! $contact = $this->getContact($data->email)) {
            return false;
        }

        $account = auth()->user()->account;
        $accountGateway = $account->getGatewayConfig(GATEWAY_STRIPE);

        if (! $accountGateway) {
            return false;
        }

        if ($this->getCustomer($data->id) || $this->getCustomer($data->email)) {
            return false;
        }

        return new Item($data, function ($data) use ($account, $contact, $accountGateway) {
            return [
                'contact_id' => $contact->id,
                'client_id' => $contact->client_id,
                'account_gateway_id' => $accountGateway->id,
                'token' => $data->id,
                'payment_method' => [
                    'contact_id' => $contact->id,
                    'payment_type_id' => PaymentType::parseCardType($data->card_brand),
                    'source_reference' => $data->card_id,
                    'last4' => $data->card_last4,
                    'expiration' => $data->card_exp_year . '-' . $data->card_exp_month . '-01',
                    'email' => $contact->email,
                    'currency_id' => $account->getCurrencyId(),
                ]
            ];
        });
    }
}
