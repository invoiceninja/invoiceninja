<?php

namespace App\PaymentDrivers\GiftUp;

use App\Models\Payment;
use App\PaymentDrivers\GiftUpPaymentDriver;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\Utils\Traits\MakesHash;
use App\PaymentDrivers\Common\MethodInterface;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Exceptions\PaymentFailed;
use App\Jobs\Mail\PaymentFailureMailer;
use Illuminate\Mail\Mailables\Address;
use App\Services\Email\EmailObject;
use App\Services\Email\Email;
use Illuminate\Support\Facades\App;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\SystemLog;
use App\Jobs\Util\SystemLogger;
use Illuminate\Support\Facades\Http;

class GiftUp implements MethodInterface, LivewireMethodInterface
{
    use MakesHash;

    public $driver_class;

    public function __construct(GiftUpPaymentDriver $driver_class)
    {
        $this->driver_class = $driver_class;
        $this->driver_class->init();
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

    public function paymentView($data)
    {
        $data = $this->paymentData($data);
        return render('gateways.giftup.pay', $data);
    }


     public function paymentResponse(PaymentResponseRequest $request)
    {
       
        $request->validate([
            'payment_hash' => ['required'],
            'amount' => ['required'],
            'currency' => ['required'],
            'giftcard_code' => ['required'],
        ]);
     
        $drv = $this->driver_class;

        if (
             strlen($drv->api_key) < 1
        ) {
            
            return back()->withErrors(['giftcard_code' => 'GiftUP API Key is missing.'])->withInput();

        }
       

        try {
            $_invoice = collect($drv->payment_hash->data->invoices)->first();
             
            $cli = $drv->client; 
           // check the GiftUP code validity
            $result = $this->validateGiftupCode($drv->test_mode,$request->giftcard_code, $drv->api_key);
        
            if ($result['valid']) { 
                    //first register webhooks
                $response = $this->registerGiftupWebhook(
                        $apiKey = $drv->api_key,
                        $eventType = 'gift-card-redeemed',
                        $targetUrl = $drv->webhook_url, 
                        $isTestMode = $drv->test_mode
                    );
                $redeemAmount = min($result['balance'], $request->amount);   
                 //Redeem card here.
                $result = $this->redeemGiftupCard($drv->test_mode,$request->giftcard_code, $redeemAmount, $drv->api_key,$_invoice->number);
                
     
                $transactionId = $result['transactionId'] ?? null;
                $redeemedAmount = $result['redeemedAmount'] ?? 0;
                $remainingCredit = $result['remainingCredit'] ?? 0; 
                if ($transactionId && $redeemedAmount != 0) {
                    // Proceed with marking payment
                  return $this->processSuccessfulPayment($redeemedAmount,$transactionId); 
                } else {
                      $reason = 'Gift card redemption failed. Possible reasons: already redeemed, insufficient balance, voided, or invalid state.';

                      $this->driver_class->sendFailureMail($reason);
                    
                      throw new PaymentFailed($reason, 422);
                }
             
    
            } else {
                return back()->withErrors(['giftcard_code' => $result['reason']])->withInput();
            }
        
        } catch (\Throwable $e) {
            PaymentFailureMailer::dispatch($drv->client, $drv->payment_hash->data, $drv->client->company, $request->amount);
            throw new PaymentFailed('Error during GiftUp payment : ' . $e->getMessage());
            
        }
    }
    

    private function processSuccessfulPayment($amount, $transactionId)
    {
       
    
        // Create a payment with pending status
        $payment_record = [
            'amount' => $amount,
            'payment_type' => PaymentType::GIFTUP,
            'gateway_type_id' => GatewayType::GIFTUP,
            'transaction_reference' => $transactionId,
        ];
        
        $_invoice = collect($this->driver_class->payment_hash->data->invoices)->first();
        $invoice = Invoice::where('number', $_invoice->number)->first();
        $original_balance = $invoice->balance;
        
        $payment = $this->driver_class->createPayment($payment_record, Payment::STATUS_COMPLETED);
       
        if ($amount < $original_balance) {  
            $invoice = $invoice->fresh();
            // Now force balance manually — AFTER service methods, to avoid it being overwritten
            $invoice->balance = max(0, $original_balance - $amount);
            $paidToDate = ($invoice->amount - $original_balance) + $amount;
  
         
            // You can use this value to update or verify consistency
            $invoice->paid_to_date = $paidToDate;
            $invoice->status_id = Invoice::STATUS_PARTIAL;
         
            $invoice->save();
        } 

        return redirect()->route('client.payments.show', ['payment' => $payment->hashed_id]);
    }


    

    public function refund(Payment $payment, $amount)
    {
       return false;
    }

    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.giftup.pay_livewire';
    }

    /**
     * @inheritDoc
     */
    public function paymentData(array $data): array
    {
        $data['gateway'] = $this->driver_class;
        $data['amount'] = $data['total']['amount_with_fee'];
        $data['currency'] = $this->driver_class->client->getCurrencyCode();

        return $data;
    }
    
    // Giftup functions here

    public function authorizeGiftup(){
        
    }
    
  
    //GET https://api.giftup.app/gift-cards/{code}
    public function validateGiftupCode($testMode,$code, $apiKey){
        
        $url = "https://api.giftup.app/gift-cards/" . urlencode($code);

        $headers = [
            "Authorization" => "Bearer {$apiKey}",
            "Accept" => "application/json"
        ];
        
        if ($testMode) {
            $headers["x-giftup-testmode"] = "true";
        }
    
        try {
            $response = Http::withHeaders($headers)->get($url);
            
            $httpCode = $response->status();
            $responseBody = $response->body();
            
            // Log the response
            SystemLogger::dispatch(
                ['response' => $responseBody, 'status_code' => $httpCode, 'request_url' => $url],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                $httpCode === 200 ? SystemLog::EVENT_GATEWAY_SUCCESS : SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_GIFTUP,
                $this->driver_class->client,
                $this->driver_class->client->company ?? $this->driver_class->company_gateway->company,
            );
            
            if ($httpCode == 404) {
                return [
                    'valid' => false,
                    'reason' => 'Giftcard with that code does not exist.'
                ];
            }
            
            if ($httpCode !== 200) {
                throw new PaymentFailed("API returned status code {$httpCode}. Response: {$responseBody}");
            }
        
            $data = $response->json();
        
            // Basic validation logic
            if (!$data['canBeRedeemed'] || $data['hasExpired'] || $data['notYetValid'] || $data['isVoided']) {
                return [
                    'valid' => false,
                    'reason' => 'Gift card cannot be redeemed.Might be expired or not valid yet or has already been redeemed or voided'
                ];
            }
         
            
            if ($data['remainingValue'] <= 0) {
                return [
                    'valid' => false,
                    'reason' => 'Gift card has no remaining balance.',
                    'balance' => 0
                ];
            }
    
        
            return [
                'valid' => true,
                'code' => $data['code'],
                'balance' => $data['remainingValue'],
                'message' => $data['message'] ?? null,
                'title' => $data['title'] ?? null,
                'expiresOn' => $data['expiresOn'] ?? null
            ];
            
        } catch (\Exception $e) {
            SystemLogger::dispatch(
                ['error' => $e->getMessage(), 'request_url' => $url],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_GIFTUP,
                $this->driver_class->client,
                $this->driver_class->client->company ?? $this->driver_class->company_gateway->company,
            );
            
            throw new PaymentFailed("HTTP Error: " . $e->getMessage());
        }
    }
    
    
    //POST https://api.giftup.app/gift-cards/{code}/redeem
    public function redeemGiftupCard($testMode,$code, $amount, $apiKey, $invoiceId)
    {
        $url = "https://api.giftup.app/gift-cards/" . urlencode($code) . "/redeem";
    
        $headers = [
            "Authorization" => "Bearer {$apiKey}",
            "Accept" => "application/json",
            "Content-Type" => "application/json"
        ];
    
        // Add test mode header if enabled
        if ($testMode) {
            $headers["x-giftup-testmode"] = "true";
        }
    
        $body = [
            "amount" => $amount,
            "units" => null,
            "reason" => "Used with Invoice #{$invoiceId}",
            "locationId" => null,
            "metadata" => [
                "ExternalOrderId" => $invoiceId
            ]
        ];
    
        try {
            $response = Http::withHeaders($headers)
                ->post($url, $body);
             
            $httpCode = $response->status();
            $responseBody = $response->body();
            
            // Log the response
            SystemLogger::dispatch(
                ['response' => $responseBody, 'status_code' => $httpCode, 'request_url' => $url, 'request_body' => $body],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                $httpCode === 200 ? SystemLog::EVENT_GATEWAY_SUCCESS : SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_GIFTUP,
                $this->driver_class->client,
                $this->driver_class->client->company ?? $this->driver_class->company_gateway->company,
            );
    
            if ($httpCode !== 200) {
                throw new PaymentFailed("API Error ({$httpCode}): " . $responseBody);
            }
    
            return $response->json();
            
        } catch (\Exception $e) {
            SystemLogger::dispatch(
                ['error' => $e->getMessage(), 'request_url' => $url, 'request_body' => $body],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_GIFTUP,
                $this->driver_class->client,
                $this->driver_class->client->company ?? $this->driver_class->company_gateway->company,
            );
            
            throw new PaymentFailed("HTTP Error: " . $e->getMessage());
        }
    }
    
    public function registerGiftupWebhook(string $apiKey, string $eventType, string $targetUrl, bool $isTestMode = false): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];
    
        try {
            // Step 1: Get existing webhooks
            $response = Http::withHeaders($headers)->get('https://api.giftup.app/hooks');
            $responseBody = $response->body();
            $httpCode = $response->status();
            
            // Log the response
            SystemLogger::dispatch(
                ['response' => $responseBody, 'status_code' => $httpCode, 'request_url' => 'https://api.giftup.app/hooks'],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                $httpCode === 200 ? SystemLog::EVENT_GATEWAY_SUCCESS : SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_GIFTUP,
                $this->driver_class->client,
                $this->driver_class->client->company ?? $this->driver_class->company_gateway->company,
            );
    
            if (!$response->successful()) {
                return ['success' => false, 'message' => 'Failed to retrieve existing webhooks.'];
            }
    
            $existingWebhooks = $response->json();
    
            // Step 2: Check if webhook with same URL, eventType, and testMode exists
            foreach ($existingWebhooks as $webhook) {
                if (
                    $webhook['url'] === $targetUrl &&
                    strtolower($webhook['eventType']) === strtolower($eventType) &&
                    $webhook['isTestMode'] == $isTestMode
                ) {
                    return ['success' => true, 'message' => 'Webhook already exists.', 'id' => $webhook['id']];
                }
            }
    
            // Step 3: Create webhook if not found
            $payload = [
                'targetUrl' => $targetUrl, 
                'isTestMode' => $isTestMode
            ];
    
            $subscribeUrl = "https://api.giftup.app/hooks/{$eventType}/subscribe";
    
            $createResponse = Http::withHeaders($headers)
                ->post($subscribeUrl, $payload);
             
            $statusCode = $createResponse->status();
            $createResponseBody = $createResponse->body();
            
            // Log the webhook creation response
            SystemLogger::dispatch(
                ['response' => $createResponseBody, 'status_code' => $statusCode, 'request_url' => $subscribeUrl, 'request_body' => $payload],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                $statusCode === 201 ? SystemLog::EVENT_GATEWAY_SUCCESS : SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_GIFTUP,
                $this->driver_class->client,
                $this->driver_class->client->company ?? $this->driver_class->company_gateway->company,
            );
    
            if ($statusCode === 201) {
                $result = $createResponse->json();
                return ['success' => true, 'message' => 'Webhook created.', 'id' => $result['id'] ?? null];
            } else {
                return ['success' => false, 'message' => 'Failed to create webhook.', 'response' => $createResponseBody];
            }
            
        } catch (\Exception $e) {
            SystemLogger::dispatch(
                ['error' => $e->getMessage(), 'request_url' => 'https://api.giftup.app/hooks'],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_GIFTUP,
                $this->driver_class->client,
                $this->driver_class->client->company ?? $this->driver_class->company_gateway->company,
            );
            
            return ['success' => false, 'message' => 'Exception occurred: ' . $e->getMessage()];
        }
    }



}
