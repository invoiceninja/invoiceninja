<?php

namespace App\PaymentDrivers;

use App\Exceptions\PaymentFailed;
use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Mail\Admin\PendingPaymentObject;
use App\Models\Client;
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use MercadoPago\Item;
use MercadoPago\Preference;
use MercadoPago\SDK;

class MercadoPagoPaymentDriver extends BaseDriver
{
    const PAYMENT_SUCCESS = 1;
    const PAYMENT_FAILED = 0;
    const PAYMENT_PENDING = -1;

    public $gateway_name;

    /**
     * MercadoPagoPaymentDriver constructor.
     *
     * @param CompanyGateway $company_gateway
     * @param Client|null $client
     * @param bool $invitation
     */
    public function __construct(CompanyGateway $company_gateway, Client $client = null, $invitation = false)
    {
        parent::__construct($company_gateway, $client, $invitation);
        SDK::setAccessToken($this->company_gateway->getConfigField('accessToken'));
        $this->gateway_name =  $company_gateway->label;
    }

    /**
     * Authorize a payment method.
     *
     * Returns a reusable token for storage for future payments
     *
     * @param array $data
     * @return Factory|View Return a view for collecting payment method information
     */
    public function authorizeView(array $data)
    {
        return render('gateways.mercadopago.authorize', $data);
    }

    /**
     * The payment authorization response
     *
     * @param  Request $request
     * @return RedirectResponse Return a response for collecting payment method information
     */
    public function authorizeResponse(Request $request): RedirectResponse
    {
        return redirect()->route('client.payment_methods.index');
    }

    /**
     * Process a payment
     *
     * @param array $data
     * @return Factory|View Return a view for the payment
     * @throws Exception
     */
    public function processPaymentView(array $data)
    {
        $preferenceId = $this->createPreference($data['amount_with_fee']);
        $data['preference_id'] = $preferenceId;
        $data['gateway'] = $this;
        $data['payment_hash'] = $this->payment_hash;
        $data['public_key'] = $this->company_gateway->getConfigField('publicKey');
        return render('gateways.mercadopago.pay', $data);
    }

    private function sendEmailForPendingPayment(string $amount)
    {
        $this->company_gateway->company->company_users->each(function ($company_user) use($amount){
            $mail_obj = (new PendingPaymentObject($this->client, $this->gateway_name, $this->company_gateway->company, $amount))->build();
            $nmo = new NinjaMailerObject;
            $nmo->mailable = new NinjaMailer($mail_obj);
            $nmo->company = $this->company_gateway->company;
            $nmo->to_user = $company_user->user;
            $nmo->settings = $this->client->getMergedSettings();

            NinjaMailerJob::dispatch($nmo);
        });
    }

    /**
     * @returns string
     * @throws Exception
     */
    private function createPreference($total): string
    {
        $item = new Item();
        $item->title = 'Invoice for ' . $this->client->name;
        $item->quantity = 1;
        $item->unit_price = $total;

        $preference = new Preference();
        $preference->items = [$item];
        $params = [
            'company_gateway_id' => $this->getCompanyGatewayId(),
            'payment_method_id' => '1',
            'payment_hash' => $this->payment_hash->hash,
            'amount' => $total
        ];
        $preference->back_urls = array(
            "success" => route('client.payments.response.get', array_merge($params, ['success' => self::PAYMENT_SUCCESS])),
            "failure" => route('client.payments.response.get', array_merge($params, ['success' => self::PAYMENT_FAILED])),
            "pending" => route('client.payments.response.get', array_merge($params, ['success' => self::PAYMENT_PENDING]))
        );
        $preference->auto_return = "approved";
        $preference->save();
        return $preference->id;
    }

    /**
     * Process payment response
     *
     * @param Request $request
     * @return Application|RedirectResponse|Redirector   Return a response for the payment
     * @throws PaymentFailed
     */
    public function processPaymentResponse(Request $request)
    {
        try {
            $payment_status = $request->get('success', self::PAYMENT_FAILED);
            if($payment_status == self::PAYMENT_FAILED) {
                return redirect('client/invoices')
                    ->withErrors(ctrans('texts.payment_failed_client_feedback', ['payment_gateway' => $this->gateway_name]));
            } elseif($payment_status == self::PAYMENT_PENDING) {
                $this->sendEmailForPendingPayment((float) $request->get('amount'));
                return redirect('client/invoices')
                    ->withErrors(ctrans('texts.pending_payment_client_feedback', ['payment_gateway' => $this->gateway_name]));
            }
            $data['gateway_type_id'] = GatewayType::CREDIT_CARD;
            $data['amount'] = (float) $request->get('amount');
            $data['payment_type'] = PaymentType::CREDIT;
            $data['transaction_reference'] = $request->get('IdPagamentoCliente');
            $this->createPayment($data);
            return redirect('client/invoices');
        } catch (Exception $e) {
            PaymentFailureMailer::dispatch($this->client, $e->getMessage(), $this->company_gateway->company, $this->payment_hash);
            Log::error($e);
            throw new PaymentFailed('Failed to process the payment.', 500);
        }
    }

    /**
     * Executes a refund attempt for a given amount with a transaction_reference.
     *
     * @param Payment $payment The Payment Object
     * @param $amount
     * @param bool $return_client_response Whether the method needs to return a response (otherwise we assume an unattended payment)
     * @return mixed
     */
    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        return null;
    }

    /**
     * Process an unattended payment.
     *
     * @param ClientGatewayToken $cgt The client gateway token object
     * @param PaymentHash $payment_hash The Payment hash containing the payment meta data
     * @return void The payment response
     */
    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
    }

    /**
     * Set the inbound request payment method type for access.
     *
     * @param int $payment_method_id The Payment Method ID
     */
    public function setPaymentMethod($payment_method_id)
    {
        $this->payment_method = $payment_method_id;
        return $this;
    }

    /**
     * Returns the gateway types.
     */
    public function gatewayTypes(): array
    {
        return [
            GatewayType::CREDIT_CARD,
        ];
    }
}
