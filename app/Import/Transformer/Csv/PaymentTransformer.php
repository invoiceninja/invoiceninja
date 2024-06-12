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

namespace App\Import\Transformer\Csv;

use App\Import\ImportException;
use App\Import\Transformer\BaseTransformer;

/**
 * Class PaymentTransformer.
 */
class PaymentTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return array
     */
    public function transform($data)
    {
        nlog($data);
        $client_id = $this->getClient(
            $this->getString($data, 'payment.client_id'),
            $this->getString($data, 'payment.client_id')
        );

        if (empty($client_id)) {
            throw new ImportException('Could not find client.');
        }

        $transformed = [
            'company_id' => $this->company->id,
            'number' => $this->getString($data, 'payment.number'),
            'user_id' => $this->getString($data, 'payment.user_id'),
            'amount' => $this->getFloat($data, 'payment.amount'),
            'refunded' => $this->getFloat($data, 'payment.refunded'),
            'applied' => $this->getFloat($data, 'payment.applied'),
            'transaction_reference' => $this->getString(
                $data,
                'payment.transaction_reference '
            ),
            'date' => isset($data['payment.date']) ? $this->parseDate($data['payment.date']) : date('y-m-d'),
            'private_notes' => $this->getString($data, 'payment.private_notes'),
            'custom_value1' => $this->getString($data, 'payment.custom_value1'),
            'custom_value2' => $this->getString($data, 'payment.custom_value2'),
            'custom_value3' => $this->getString($data, 'payment.custom_value3'),
            'custom_value4' => $this->getString($data, 'payment.custom_value4'),
            'client_id' => $client_id,
        ];

        if (
            isset($data['payment.invoice_number']) &&
            ($invoice_id = $this->getInvoiceId($data['payment.invoice_number']))
        ) {
            $transformed['invoices'] = [
                [
                    'invoice_id' => $invoice_id,
                    'amount' => $transformed['amount'] ?? null,
                ],
            ];
        }

        return $transformed;
    }
}
