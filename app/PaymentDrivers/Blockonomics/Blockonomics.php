<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Blockonomics;

use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\PaymentType;
use App\Models\PaymentHash;
use App\Models\Invoice;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\Utils\BcMath;
use App\Exceptions\PaymentFailed;
use Illuminate\Support\Facades\Http;
use App\Jobs\Mail\PaymentFailureMailer;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\BlockonomicsPaymentDriver;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;

class Blockonomics implements LivewireMethodInterface
{
    use MakesHash;
    private string $test_txid = 'WarningThisIsAGeneratedTestPaymentAndNotARealBitcoinTransaction';

    public function __construct(public BlockonomicsPaymentDriver $blockonomics)
    {
    }

    public function authorizeView($data)
    {
    }

    public function authorizeRequest($request)
    {
    }

    public function authorizeResponse($request)
    {
    }


    public function getBTCAddress(): array
    {
        $api_key = $this->blockonomics->company_gateway->getConfigField('apiKey');
        $company_key = $this->blockonomics->company_gateway->company->company_key;

        if (!$api_key) {
            return ['success' => false, 'message' => 'Please enter a valid API key'];
        }

        $url = 'https://www.blockonomics.co/api/new_address?match_callback=' . $company_key;

        $response = Http::withToken($api_key)
                        ->post($url, []);

        nlog($response->body());

        if ($response->status() == 401) {
            return ['success' => false, 'message' => 'API Key is incorrect'];
        };

        if ($response->successful()) {
            if (isset($response->object()->address)) {
                return ['success' => true, 'address' => $response->object()->address];
            } else {
                return ['success' => false, 'message' => 'Address not returned'];
            }
        } else {
            return ['success' => false, 'message' => "Could not generate new address (This may be a temporary error. Please try again). \n\n<br><br> If this continues, please ask website administrator to check blockonomics registered email address for error messages"];
        }

    }

    public function getBTCPrice()
    {

        $r = Http::get('https://www.blockonomics.co/api/price', ['currency' => $this->blockonomics->client->getCurrencyCode()]);

        return $r->successful() ? $r->object()->price : 'Something went wrong';

    }

    public function paymentData(array $data): array
    {

        $btc_price = $this->getBTCPrice();
        $btc_address = $this->getBTCAddress();
        $data['error'] = null;
        if (!$btc_address['success']) {
            $data['error'] = $btc_address['message'];
        }
        $fiat_amount = $data['total']['amount_with_fee'];
        $btc_amount = $fiat_amount / $btc_price;
        $_invoice = collect($this->blockonomics->payment_hash->data->invoices)->first();
        $data['gateway'] = $this->blockonomics;
        $data['company_gateway_id'] = $this->blockonomics->getCompanyGatewayId();
        $data['amount'] = $fiat_amount;
        $data['currency'] = $this->blockonomics->client->getCurrencyCode();
        $data['btc_amount'] = number_format($btc_amount, 10, '.', '');
        $data['btc_address'] = $btc_address['address'] ?? '';
        $data['btc_price'] = $btc_price;
        $data['invoice_number'] = $_invoice->invoice_number;

        return $data;
    }

    public function livewirePaymentView(array $data): string
    {
        return 'gateways.blockonomics.pay_livewire';
    }

    public function paymentView($data)
    {
        $data = $this->paymentData($data);

        return render('gateways.blockonomics.pay', $data);
    }


    public function paymentResponse(PaymentResponseRequest $request)
    {
        $request->validate([
            'payment_hash' => ['required'],
            'amount' => ['required'],
            'currency' => ['required'],
            'txid' => ['required'],
            'payment_method_id' => ['required'],
            'btc_address' => ['required'],
            'btc_amount' => ['required'],
            'btc_price' => ['required'],
        ]);

        $this->blockonomics->payment_hash = PaymentHash::where('hash', $request->payment_hash)->firstOrFail();

        // Calculate fiat amount from Bitcoin
        $amount_received_satoshis = $request->btc_amount;
        $amount_satoshis_in_one_btc = 100000000;
        $amount_received_btc = $amount_received_satoshis / $amount_satoshis_in_one_btc;
        $price_per_btc_in_fiat = $request->btc_price;
        $fiat_amount = round(($price_per_btc_in_fiat * $amount_received_btc), 2);

        // Get the expected amount from payment hash
        $payment_hash_data = $this->blockonomics->payment_hash->data;
        $expected_amount = $payment_hash_data->amount_with_fee;

        // Adjust invoice allocations to match actual received amount if the amounts don't match
        if (!BcMath::equal($fiat_amount, $expected_amount)) {
            $this->adjustInvoiceAllocations($fiat_amount);
        }

        try {
            $data = [
                'amount' => $fiat_amount,
                'payment_method_id' => $request->payment_method_id,
                'payment_type' => PaymentType::CRYPTO,
                'gateway_type_id' => GatewayType::CRYPTO,
            ];

            // Append a random value to the transaction reference for test payments
            $testTxid = $this->test_txid;
            $data['transaction_reference'] = ($request->txid === $testTxid)
                ? $request->txid . bin2hex(random_bytes(16))
                : $request->txid;

            // Determine payment status
            $statusId = match($request->status) {
                2 => Payment::STATUS_COMPLETED,
                default => Payment::STATUS_PENDING
            };

            $payment = $this->blockonomics->createPayment($data, $statusId);
            $payment->private_notes = "{$request->btc_address} - {$request->btc_amount}";
            $payment->save();

            SystemLogger::dispatch(
                ['response' => $payment, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_BLOCKONOMICS,
                $this->blockonomics->client,
                $this->blockonomics->client->company,
            );

            return redirect()->route('client.payments.show', ['payment' => $payment->hashed_id]);

        } catch (\Throwable $e) {
            $blockonomics = $this->blockonomics;
            PaymentFailureMailer::dispatch(
                $blockonomics->client,
                $blockonomics->client->company,
                $fiat_amount
            );
            throw new PaymentFailed('Error during Blockonomics payment: ' . $e->getMessage());
        }
    }

    /**
     * Adjust invoice allocations to match the actual amount received
     * Only modifies the amounts in the PaymentHash, never the actual invoices
     */
    private function adjustInvoiceAllocations(float $amount_received): void
    {
        $payment_hash_data = $this->blockonomics->payment_hash->data;

        // Get the invoices array from payment hash data
        $invoices = $payment_hash_data->invoices ?? [];

        if (empty($invoices)) {
            return;
        }

        $remaining_amount = $amount_received;
        $adjusted_invoices = [];

        // Iterate through invoices and allocate up to the amount received
        foreach ($invoices as $invoice) {
            if ($remaining_amount <= 0) {
                // No more funds to allocate, drop remaining invoices
                break;
            }

            $invoice_amount = $invoice->amount;

            if (BcMath::greaterThan($remaining_amount, $invoice_amount)) {
                // Full payment for this invoice - keep all original data
                $adjusted_invoices[] = (object)[
                    'invoice_id' => $invoice->invoice_id,
                    'amount' => $invoice_amount,
                    'formatted_amount' => number_format($invoice_amount, 2),
                    'formatted_currency' => '$' . number_format($invoice_amount, 2),
                    'number' => $invoice->number,
                    'date' => $invoice->date,
                    'due_date' => $invoice->due_date ?? '',
                    'terms' => $invoice->terms ?? '',
                    'invoice_number' => $invoice->invoice_number,
                    'additional_info' => $invoice->additional_info ?? '',
                ];
                $remaining_amount -= $invoice_amount;
            } else {
                // Partial payment for this invoice - adjust the amount
                $adjusted_invoices[] = (object)[
                    'invoice_id' => $invoice->invoice_id,
                    'amount' => round($remaining_amount, 2),
                    'formatted_amount' => number_format($remaining_amount, 2),
                    'formatted_currency' => '$' . number_format($remaining_amount, 2),
                    'number' => $invoice->number,
                    'date' => $invoice->date,
                    'due_date' => $invoice->due_date ?? '',
                    'terms' => $invoice->terms ?? '',
                    'invoice_number' => $invoice->invoice_number,
                    'additional_info' => $invoice->additional_info ?? '',
                ];
                $remaining_amount = 0;
            }
        }

        // Update the payment hash with adjusted invoice allocations
        $payment_hash_data->invoices = $adjusted_invoices;
        $payment_hash_data->amount_with_fee = $amount_received; // Critical: Update total amount

        $this->blockonomics->payment_hash->data = $payment_hash_data;
        $this->blockonomics->payment_hash->save();
    }
    // Not supported yet
    public function refund(Payment $payment, $amount)
    {
        return;
    }
}
