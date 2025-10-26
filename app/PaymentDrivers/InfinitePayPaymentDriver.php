<?php

/**
 * InfinitePay Payment Driver for Invoice Ninja
 * 
 * A Brazilian Payment Gateway Native for Invoice Ninja
 * File: app/PaymentDrivers/InfinitePayPaymentDriver.php
 */

namespace App\PaymentDrivers;

use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\GatewayType;
use App\Models\SystemLog;
use App\PaymentDrivers\BaseDriver;
use App\Jobs\Util\SystemLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class InfinitePayPaymentDriver extends BaseDriver
{
    public $refundable = true;
    public $token_billing = false;
    public $can_authorise_and_capture = true;

    private $api_url = 'https://api.infinitepay.io/v2';

    /**
     * Retorna os tipos de gateway suportados
     */
    public static function getPaymentTypes(): array
    {
        return [
            GatewayType::PIX => true,
            GatewayType::CREDIT_CARD => true,
            GatewayType::BANK_TRANSFER => true,
        ];
    }

    /**
     * Inicializa o driver
     */
    public function init()
    {
        return $this;
    }

    /**
     * Define as views do gateway
     */
    public function setPaymentMethod($payment_method_id)
    {
        $this->payment_method = $payment_method_id;

        switch ($payment_method_id) {
            case GatewayType::PIX:
                $this->payment_driver = new InfinitePayPix($this);
                break;
            case GatewayType::CREDIT_CARD:
                $this->payment_driver = new InfinitePayCreditCard($this);
                break;
            case GatewayType::BANK_TRANSFER:
                $this->payment_driver = new InfinitePayBankSlip($this);
                break;
            default:
                $this->payment_driver = new InfinitePayPix($this);
        }

        return $this;
    }

    /**
     * Autoriza um pagamento
     */
    public function authorizeView(array $data)
    {
        return $this->payment_driver->authorizeView($data);
    }

    /**
     * Processa autorização
     */
    public function authorizeResponse(Request $request)
    {
        return $this->payment_driver->authorizeResponse($request);
    }

    /**
     * Processa o pagamento
     */
    public function processPaymentView(array $data)
    {
        return $this->payment_driver->processPaymentView($data);
    }

    /**
     * Resposta do processamento
     */
    public function processPaymentResponse(Request $request)
    {
        return $this->payment_driver->processPaymentResponse($request);
    }

    /**
     * Processa webhook
     */
    public function processWebhookRequest(Request $request)
    {
        $payload = $request->all();

        SystemLogger::dispatch(
            $payload,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_INFINITEPAY,
            $this->client,
            $this->company_gateway->company_id
        );

        $event = $payload['event'] ?? null;
        $data = $payload['data'] ?? null;

        if (!$event || !$data) {
            return response()->json(['message' => 'Invalid webhook'], 400);
        }

        switch ($event) {
            case 'charge.paid':
                return $this->handlePaidWebhook($data);
            case 'charge.refunded':
                return $this->handleRefundWebhook($data);
            case 'charge.failed':
                return $this->handleFailedWebhook($data);
            default:
                return response()->json(['message' => 'Event not handled'], 200);
        }
    }

    /**
     * Trata webhook de pagamento confirmado
     */
    private function handlePaidWebhook($data)
    {
        $invoice_id = $data['metadata']['invoice_id'] ?? null;
        $charge_id = $data['id'] ?? null;

        if (!$invoice_id) {
            return response()->json(['message' => 'Invoice ID not found'], 400);
        }

        $invoice = \App\Models\Invoice::find($invoice_id);

        if (!$invoice) {
            return response()->json(['message' => 'Invoice not found'], 404);
        }

        // Verifica se já não foi pago
        if ($invoice->status_id == \App\Models\Invoice::STATUS_PAID) {
            return response()->json(['message' => 'Already paid'], 200);
        }

        // Cria o pagamento
        $payment = Payment::create([
            'company_id' => $invoice->company_id,
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'amount' => $data['amount'] / 100,
            'status_id' => Payment::STATUS_COMPLETED,
            'date' => now(),
            'transaction_reference' => $charge_id,
            'type_id' => $this->getPaymentTypeFromMethod($data['payment_method'] ?? 'pix'),
            'company_gateway_id' => $this->company_gateway->id,
        ]);

        $invoice->service()->markPaid()->save();

        return response()->json(['message' => 'Payment processed'], 200);
    }

    /**
     * Trata webhook de reembolso
     */
    private function handleRefundWebhook($data)
    {
        $charge_id = $data['id'] ?? null;

        $payment = Payment::where('transaction_reference', $charge_id)->first();

        if ($payment) {
            $payment->refunded = now();
            $payment->status_id = Payment::STATUS_REFUNDED;
            $payment->save();
        }

        return response()->json(['message' => 'Refund processed'], 200);
    }

    /**
     * Trata webhook de falha
     */
    private function handleFailedWebhook($data)
    {
        // Adicionar lógica se necessário
        return response()->json(['message' => 'Failure noted'], 200);
    }

    /**
     * Processa reembolso
     */
    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $charge_id = $payment->transaction_reference;

        $response = $this->makeRequest('POST', "/charges/{$charge_id}/refund", [
            'amount' => (int)($amount * 100)
        ]);

        if ($response && isset($response['status']) && $response['status'] == 'refunded') {
            $payment->refunded = now();
            $payment->status_id = Payment::STATUS_REFUNDED;
            $payment->save();

            return [
                'transaction_reference' => $response['id'],
                'transaction_response' => json_encode($response),
                'success' => true,
                'description' => 'Reembolso processado com sucesso',
                'code' => '200',
            ];
        }

        return [
            'transaction_reference' => null,
            'transaction_response' => json_encode($response),
            'success' => false,
            'description' => 'Falha ao processar reembolso',
            'code' => '500',
        ];
    }

    /**
     * Faz requisição para API da InfinitePay
     */
    public function makeRequest($method, $endpoint, $data = [])
    {
        $api_key = $this->company_gateway->getConfigField('apiKey');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ])->$method($this->api_url . $endpoint, $data);

            if ($response->successful()) {
                return $response->json();
            }

            SystemLogger::dispatch(
                $response->json(),
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_INFINITEPAY,
                $this->client,
                $this->company_gateway->company_id
            );

            return null;
        } catch (\Exception $e) {
            SystemLogger::dispatch(
                ['error' => $e->getMessage()],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_INFINITEPAY,
                $this->client,
                $this->company_gateway->company_id
            );

            return null;
        }
    }

    /**
     * Obtém tipo de pagamento baseado no método
     */
    private function getPaymentTypeFromMethod($method)
    {
        $types = [
            'pix' => PaymentType::PIX,
            'credit_card' => PaymentType::CREDIT_CARD_OTHER,
            'bank_slip' => PaymentType::BANK_TRANSFER,
        ];

        return $types[$method] ?? PaymentType::PIX;
    }

    /**
     * Retorna campos de configuração do gateway
     */
    public static function getPaymentSettings(): array
    {
        return [
            'apiKey' => [
                'label' => 'API Key',
                'type' => 'text',
                'required' => true,
                'help' => 'Sua chave de API da InfinitePay'
            ],
        ];
    }
}

/**
 * Driver PIX
 */
class InfinitePayPix
{
    public $driver;

    public function __construct(InfinitePayPaymentDriver $driver)
    {
        $this->driver = $driver;
    }

    public function authorizeView(array $data)
    {
        return $this->processPaymentView($data);
    }

    public function authorizeResponse(Request $request)
    {
        return $this->processPaymentResponse($request);
    }

    public function processPaymentView(array $data)
    {
        $invoice = $data['invoices']->first();
        
        // Cria cobrança na InfinitePay
        $charge = $this->driver->makeRequest('POST', '/charges', [
            'amount' => (int)($invoice->balance * 100),
            'description' => "Fatura #{$invoice->number}",
            'customer' => [
                'name' => $invoice->client->present()->name(),
                'email' => $invoice->client->present()->email(),
                'document' => preg_replace('/[^0-9]/', '', $invoice->client->id_number ?? ''),
                'phone' => preg_replace('/[^0-9]/', '', $invoice->client->phone ?? ''),
            ],
            'metadata' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->number,
            ],
            'payment_methods' => ['pix'],
        ]);

        if (!$charge) {
            return redirect()->route('client.invoices.show', ['invoice' => $invoice->hashed_id])
                ->with('error', 'Erro ao gerar pagamento PIX');
        }

        // Salva charge_id no invoice
        $invoice->public_notes = ($invoice->public_notes ?? '') . "\nInfinitePay Charge: {$charge['id']}";
        $invoice->save();

        $data['charge'] = $charge;
        $data['gateway'] = $this->driver;

        return view('gateways.infinitepay.pix', $data);
    }

    public function processPaymentResponse(Request $request)
    {
        // Redireciona para a invoice com mensagem de aguardando pagamento
        return redirect()->route('client.invoices.show', ['invoice' => $request->invoice_hashed_id])
            ->with('message', 'Pagamento PIX gerado. Aguardando confirmação.');
    }
}

/**
 * Driver Cartão de Crédito
 */
class InfinitePayCreditCard
{
    public $driver;

    public function __construct(InfinitePayPaymentDriver $driver)
    {
        $this->driver = $driver;
    }

    public function authorizeView(array $data)
    {
        return $this->processPaymentView($data);
    }

    public function authorizeResponse(Request $request)
    {
        return $this->processPaymentResponse($request);
    }

    public function processPaymentView(array $data)
    {
        $data['gateway'] = $this->driver;
        return view('gateways.infinitepay.credit_card', $data);
    }

    public function processPaymentResponse(Request $request)
    {
        $invoice = \App\Models\Invoice::where('hashed_id', $request->invoice_hashed_id)->first();

        $charge = $this->driver->makeRequest('POST', '/charges', [
            'amount' => (int)($invoice->balance * 100),
            'description' => "Fatura #{$invoice->number}",
            'customer' => [
                'name' => $invoice->client->present()->name(),
                'email' => $invoice->client->present()->email(),
                'document' => preg_replace('/[^0-9]/', '', $invoice->client->id_number ?? ''),
            ],
            'payment_method' => 'credit_card',
            'credit_card' => [
                'number' => $request->card_number,
                'holder_name' => $request->card_holder,
                'expiration_month' => $request->expiration_month,
                'expiration_year' => $request->expiration_year,
                'cvv' => $request->cvv,
            ],
            'metadata' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->number,
            ],
        ]);

        if ($charge && $charge['status'] == 'paid') {
            $payment = Payment::create([
                'company_id' => $invoice->company_id,
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'amount' => $charge['amount'] / 100,
                'status_id' => Payment::STATUS_COMPLETED,
                'date' => now(),
                'transaction_reference' => $charge['id'],
                'type_id' => PaymentType::CREDIT_CARD_OTHER,
                'company_gateway_id' => $this->driver->company_gateway->id,
            ]);

            $invoice->service()->markPaid()->save();

            return redirect()->route('client.invoices.show', ['invoice' => $invoice->hashed_id])
                ->with('message', 'Pagamento processado com sucesso!');
        }

        return redirect()->route('client.invoices.show', ['invoice' => $invoice->hashed_id])
            ->with('error', 'Falha ao processar pagamento');
    }
}

/**
 * Driver Boleto Bancário
 */
class InfinitePayBankSlip
{
    public $driver;

    public function __construct(InfinitePayPaymentDriver $driver)
    {
        $this->driver = $driver;
    }

    public function authorizeView(array $data)
    {
        return $this->processPaymentView($data);
    }

    public function authorizeResponse(Request $request)
    {
        return $this->processPaymentResponse($request);
    }

    public function processPaymentView(array $data)
    {
        $invoice = $data['invoices']->first();

        $charge = $this->driver->makeRequest('POST', '/charges', [
            'amount' => (int)($invoice->balance * 100),
            'description' => "Fatura #{$invoice->number}",
            'customer' => [
                'name' => $invoice->client->present()->name(),
                'email' => $invoice->client->present()->email(),
                'document' => preg_replace('/[^0-9]/', '', $invoice->client->id_number ?? ''),
                'phone' => preg_replace('/[^0-9]/', '', $invoice->client->phone ?? ''),
            ],
            'metadata' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->number,
            ],
            'payment_methods' => ['bank_slip'],
            'due_date' => $invoice->due_date,
        ]);

        if (!$charge) {
            return redirect()->route('client.invoices.show', ['invoice' => $invoice->hashed_id])
                ->with('error', 'Erro ao gerar boleto');
        }

        $data['charge'] = $charge;
        $data['gateway'] = $this->driver;

        return view('gateways.infinitepay.bank_slip', $data);
    }

    public function processPaymentResponse(Request $request)
    {
        return redirect()->route('client.invoices.show', ['invoice' => $request->invoice_hashed_id])
            ->with('message', 'Boleto gerado. Aguardando pagamento.');
    }
}