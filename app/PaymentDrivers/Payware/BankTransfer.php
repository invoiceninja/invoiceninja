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

namespace App\PaymentDrivers\Payware;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Models\PaymentHash;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\PaywarePaymentDriver;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;

class BankTransfer implements MethodInterface, LivewireMethodInterface
{
    use MakesHash;

    public function __construct(protected PaywarePaymentDriver $driver)
    {
        $this->driver->init();
    }

    public function authorizeView(array $data)
    {
        // payware does not support payment method authorization/tokenization
    }

    public function authorizeResponse(Request $request)
    {
        // payware does not support payment method authorization/tokenization
    }

    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        return render('gateways.payware.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        // Handle AJAX status polling
        if ($request->has('payware_check_status')) {
            $paymentHash = PaymentHash::where('hash', $request->payment_hash)->first();

            if (!$paymentHash) {
                return response()->json(['status' => 'FAILED', 'message' => 'Payment hash not found']);
            }

            $data = (array) $paymentHash->data;
            $status = $data['payware_status'] ?? 'PENDING';

            $response = ['status' => $status];

            if ($status === 'CONFIRMED' && isset($data['payware_payment_id'])) {
                $response['redirect'] = route('client.payments.show', [
                    'payment' => $this->encodePrimaryKey($data['payware_payment_id']),
                ]);
            }

            if (in_array($status, ['DECLINED', 'FAILED'])) {
                $response['message'] = $data['payware_status_message'] ?? 'Payment was not completed.';
            }

            return response()->json($response);
        }

        // Handle final redirect after webhook confirmation
        $paymentHash = PaymentHash::where('hash', $request->payment_hash)->first();

        if ($paymentHash) {
            $data = (array) $paymentHash->data;
            $status = $data['payware_status'] ?? 'PENDING';

            if ($status === 'CONFIRMED' && isset($data['payware_payment_id'])) {
                return redirect()->route('client.payments.show', [
                    'payment' => $this->encodePrimaryKey($data['payware_payment_id']),
                ]);
            }
        }

        throw new PaymentFailed('payware: Payment was not confirmed.');
    }

    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.payware.pay_livewire';
    }

    /**
     * @inheritDoc
     */
    public function paymentData(array $data): array
    {
        $data['gateway'] = $this->driver;
        $data['amount'] = $data['total']['amount_with_fee'];
        $data['currency'] = $this->driver->client->getCurrencyCode();

        try {
            $api = $this->driver->getApi();

            $callbackUrl = $this->driver->genericWebhookUrl();

            $invoice = collect($this->driver->payment_hash->invoices())->first();
            $companyName = $this->driver->client->company->present()->name();
            $reason = $companyName . ', order #' . ($invoice->invoice_number ?? '');

            $result = $api->createTransaction(
                (float) $data['amount'],
                $data['currency'],
                $reason,
                $callbackUrl,
                $this->driver->payment_hash->hash,
            );

            // Store transaction data in payment_hash for webhook and polling
            $hashData = (array) $this->driver->payment_hash->data;
            $hashData['payware_transaction_id'] = $result['transactionId'];
            $hashData['payware_image_data'] = $result['imageData'];
            $hashData['payware_status'] = 'PENDING';
            $hashData['payware_created_at'] = time();
            $this->driver->payment_hash->data = $hashData;
            $this->driver->payment_hash->save();

            $data['transaction_id'] = $result['transactionId'];
            $data['qr_image_data'] = $result['imageData'];
            $data['time_to_live'] = 600;

        } catch (\Exception $e) {
            throw new PaymentFailed('payware: Failed to create payment - ' . $e->getMessage());
        }

        return $data;
    }
}
