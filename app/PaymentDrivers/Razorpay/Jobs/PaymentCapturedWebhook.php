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

namespace App\PaymentDrivers\Razorpay\Jobs;

use App\Libraries\MultiDB;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PaymentCapturedWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(public array $entity, public string $company_key, public int $company_gateway_id) {}

    public function handle(): void
    {
        MultiDB::findAndSetDbByCompanyKey($this->company_key);

        $company_gateway = CompanyGateway::query()
            ->where('id', $this->company_gateway_id)
            ->whereHas('company', fn ($query) => $query->where('company_key', $this->company_key))
            ->where('is_deleted', 0)
            ->first();

        if (! $company_gateway) {
            return;
        }

        $payment_hash = PaymentHash::query()
            ->whereHas('fee_invoice', fn ($query) => $query->where('company_id', $company_gateway->company_id))
            ->when(
                $this->entity['notes']['payment_hash'] ?? null,
                fn ($query, $payment_hash) => $query->where('hash', $payment_hash),
                fn ($query) => $query->where('data->order_id', $this->entity['order_id'])
            )
            ->first();

        if (! $payment_hash || $payment_hash->data->order_id !== $this->entity['order_id']) {
            return;
        }

        $company_gateway->driver($payment_hash->fee_invoice->client)
                        ->setPaymentHash($payment_hash)
                        ->setPaymentMethod(GatewayType::HOSTED_PAGE)
                        ->payment_method
                        ->recordPayment($this->entity['id']);
    }
}
