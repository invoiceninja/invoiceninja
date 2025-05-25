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

namespace App\Services\Quickbooks\Models;

use App\Models\Client;
use App\Models\Invoice;
use App\DataMapper\ClientSync;
use App\Factory\ClientFactory;
use App\Interfaces\SyncInterface;
use App\Factory\ClientContactFactory;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\Transformers\ClientTransformer;
use App\Services\Quickbooks\Transformers\PaymentTransformer;

class QbPayment implements SyncInterface
{
    public function __construct(public QuickbooksService $service)
    {
    }

    public function find(string $id): mixed
    {
        return $this->service->sdk->FindById('Payment', $id);
    }

    public function importToNinja(array $records): void
    {

        foreach ($records as $payment) {

            $payment_transformer = new PaymentTransformer($this->service->company);

            $transformed = $payment_transformer->qbToNinja($payment);

            $ninja_payment = $payment_transformer->buildPayment($payment);
            $ninja_payment->service()->applyNumber()->save();

            $payment_transformer->associatePaymentToInvoice($ninja_payment, $payment);

        }

    }

    public function syncToNinja(array $records): void
    {
        $transformer = new PaymentTransformer($this->service->company);

        foreach ($records as $record) {
            $ninja_data = $transformer->qbToNinja($record);
        }
    }

    public function syncToForeign(array $records): void
    {

    }
}
