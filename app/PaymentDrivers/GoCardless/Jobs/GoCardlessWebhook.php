<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\GoCardless\Jobs;

use App\Jobs\Mail\PaymentFailedMailer;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\Client;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\PaymentDrivers\GoCardless\HostedPaymentPage;
use App\PaymentDrivers\Stripe\Utilities;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class GoCardlessWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use Utilities;

    public $tries = 3;

    public function __construct(private array $events, private string $company_key, private int $company_gateway_id) {}

    public function handle(): void
    {
        MultiDB::findAndSetDbByCompanyKey($this->company_key);

        $company_gateway = CompanyGateway::withTrashed()->find($this->company_gateway_id);

        if (! $company_gateway) {
            nlog("GoCardless Webhook: company_gateway {$this->company_gateway_id} not found, aborting");
            return;
        }

        $company = $company_gateway->company;

        foreach ($this->events as $event) {
            try {
                nlog($event);
                nlog("GoCardless Webhook: " . $event['action']);

                if ($event['resource_type'] === 'billing_requests'
                    && $event['action'] === 'fulfilled'
                    && isset($event['links']['billing_request'])) {
                    $payment_hash = PaymentHash::query()
                        ->where('data->gocardless->billing_request', $event['links']['billing_request'])
                        ->first();

                    if ($payment_hash && ! $payment_hash->payment_id) {
                        $client = Client::query()->find(data_get($payment_hash->data, 'client_id'));
                        $gateway_type_id = (int) data_get($payment_hash->data, 'gocardless.gateway_type_id', GatewayType::DIRECT_DEBIT);

                        if ($client) {
                            $driver = $company_gateway->driver($client)
                                ->setPaymentMethod($gateway_type_id)
                                ->setPaymentHash($payment_hash);
                            $hosted_payment_page = new HostedPaymentPage($driver);
                            $hosted_payment_page->complete($hosted_payment_page->get($event['links']['billing_request']));
                        }
                    }
                }

                if ($event['resource_type'] === 'mandates' && isset($event['links']['mandate'])) {
                    $is_replacement = $event['action'] === 'replaced'
                        && isset($event['links']['new_mandate']);
                    $mandate_id = $is_replacement
                        ? $event['links']['new_mandate']
                        : $event['links']['mandate'];
                    $token = ClientGatewayToken::query()
                        ->where('company_gateway_id', $company_gateway->id)
                        ->where('token', $event['links']['mandate'])
                        ->first();

                    if (! $token && $is_replacement) {
                        $token = ClientGatewayToken::query()
                            ->where('company_gateway_id', $company_gateway->id)
                            ->where('token', $mandate_id)
                            ->first();
                    }

                    $client = $token?->client;
                    $client_hash = data_get($event, 'resource_metadata.client_hash');

                    if (! $client && $client_hash) {
                        $client = Client::query()
                            ->where('company_id', $company->id)
                            ->where('client_hash', $client_hash)
                            ->first();
                    }

                    if ($client) {
                        if ($is_replacement) {
                            $this->replaceMandate(
                                $company_gateway,
                                $client,
                                $event['links']['mandate'],
                                $mandate_id,
                            );
                        } else {
                            $this->syncMandate($company_gateway, $client, $mandate_id);
                        }
                    }
                }

                /** 2026-03-20: Set the correct payment type for the payment */
                if ($event['resource_type'] == 'payments' && $event['action'] == 'created' && isset($event['details']['scheme']) && isset($event['links']['payment'])) {

                    $scheme = $event['details']['scheme'];

                    $payment_type = HostedPaymentPage::paymentTypeForScheme($scheme);

                    $payment = Payment::query()
                                ->where('transaction_reference', $event['links']['payment'])
                                ->where('company_id', $company->id)
                                ->first();

                    if ($payment) {
                        $payment->type_id = $payment_type;
                        $payment->saveQuietly();
                    }

                }

                if (
                    $event['resource_type'] === 'payments'
                    && in_array($event['action'], ['confirmed', 'paid_out'], true)
                    && array_key_exists('payment', $event['links'] ?? [])
                ) {
                    nlog('Searching for transaction reference');

                    $payment = Payment::query()
                        ->where('transaction_reference', $event['links']['payment'])
                        ->where('company_id', $company->id)
                        ->first();

                    if ($payment) {
                        /** Idempotency: skip if already in a terminal/post-completed state so retries
                            don't regress a REFUNDED/PARTIALLY_REFUNDED payment back to COMPLETED. */
                        if (in_array($payment->status_id, [Payment::STATUS_COMPLETED, Payment::STATUS_REFUNDED, Payment::STATUS_PARTIALLY_REFUNDED], true)) {
                            nlog('GoCardless: payment already settled, skipping');
                        } else {
                            $payment->status_id = Payment::STATUS_COMPLETED;
                            $payment->save();
                            nlog('GoCardless completed');
                        }
                    } else {
                        nlog('I was unable to find the payment for this reference');
                    }
                    //finalize payments on invoices here.
                }

                if ($event['resource_type'] === 'payments'
                    && $event['action'] === 'failed'
                    && data_get($event, 'details.will_attempt_retry') === true) {
                    nlog('GoCardless: payment failure will be retried, keeping payment pending');
                    continue;
                }

                if (in_array($event['action'], ['cancelled', 'failed', 'late_failure_settled'], true) && array_key_exists('payment', $event['links'] ?? [])) {
                    $payment = Payment::query()
                        ->where('transaction_reference', $event['links']['payment'])
                        ->where('company_id', $company->id)
                        ->first();

                    if ($payment && !in_array($payment->status_id, [Payment::STATUS_FAILED, Payment::STATUS_CANCELLED])) {

                        if ($payment->status_id == Payment::STATUS_PENDING
                            || in_array($event['action'], ['failed', 'late_failure_settled'], true)) {
                            $payment->service()->deletePayment();
                        }

                        $payment->status_id = Payment::STATUS_FAILED;
                        $payment->save();

                        $payment_hash = PaymentHash::where('payment_id', $payment->id)->first();
                        $error = '';

                        if (isset($event['details']['description'])) {
                            $error = $event['details']['description'];
                        }

                        PaymentFailedMailer::dispatch(
                            $payment_hash,
                            $payment->client->company,
                            $payment->client,
                            $error
                        );
                    }
                }
            } catch (\Throwable $exception) {
                nlog("GoCardless Webhook: failed to process event " . ($event['id'] ?? 'unknown') . " action=" . $event['action'] . " :: " . $exception->getMessage());

                throw $exception;
            }
        }
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("gocardless-webhook-{$this->company_key}-{$this->company_gateway_id}"))
        ->releaseAfter(15)
        ->expireAfter(60)];
    }

    protected function syncMandate(CompanyGateway $company_gateway, Client $client, string $mandate_id): void
    {
        $driver = $company_gateway->driver($client)
            ->setPaymentMethod(GatewayType::DIRECT_DEBIT);
        $hosted_payment_page = new HostedPaymentPage($driver);
        $hosted_payment_page->syncMandate($mandate_id);
    }

    protected function replaceMandate(
        CompanyGateway $company_gateway,
        Client $client,
        string $mandate_id,
        string $new_mandate_id,
    ): void {
        $driver = $company_gateway->driver($client)
            ->setPaymentMethod(GatewayType::DIRECT_DEBIT);
        $hosted_payment_page = new HostedPaymentPage($driver);
        $hosted_payment_page->replaceMandate($mandate_id, $new_mandate_id);
    }
}
