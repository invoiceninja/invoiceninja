<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers;

use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\HtmlEngine;
use App\Utils\Number;
use App\Utils\Traits\MakesHash;

/**
 * Class CustomPaymentDriver.
 */
class CustomPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $token_billing = false;

    public $can_authorise_credit_card = false;

    /**
     * Returns the gateway types.
     */
    public function gatewayTypes(): array
    {
        $types = [
            GatewayType::CUSTOM,
        ];

        return $types;
    }

    public function setPaymentMethod($payment_method_id)
    {
        $this->payment_method = $payment_method_id;

        return $this;
    }

    /**
     * View for displaying custom content of the driver.
     *
     * @param array $data
     * @return mixed
     */
    public function processPaymentView($data)
    {
        $variables = [];

        if (count($this->payment_hash->invoices()) > 0) {
            $invoice_id = $this->decodePrimaryKey($this->payment_hash->invoices()[0]->invoice_id);
            $invoice = Invoice::withTrashed()->find($invoice_id);

            $variables = (new HtmlEngine($invoice->invitations->first()))->generateLabelsAndValues();
        }

        $variables['values']['$invoices'] = collect($this->payment_hash->invoices())->pluck('invoice_number')->implode(',');
        $variables['labels']['$invoices_label'] = ctrans('texts.invoice_number_short');

        $data['title'] = $this->company_gateway->getConfigField('name');
        $data['instructions'] = strtr($this->company_gateway->getConfigField('text'), $variables['values']);

        $this->payment_hash->data = array_merge((array) $this->payment_hash->data, $data);
        $this->payment_hash->save();

        $data['gateway'] = $this;

        return render('gateways.custom.payment', $data);
    }

    /**
     * Processing method for payment. Should never be reached with this driver.
     *
     * @return mixed
     */
    public function processPaymentResponse($request)
    {
        return redirect()->route('client.invoices');
    }

    /**
     * Detach payment method from custom payment driver.
     *
     * @param ClientGatewayToken $token
     * @return void
     */
    public function detach(ClientGatewayToken $token)
    {
        // Driver doesn't support this feature.
    }
}
