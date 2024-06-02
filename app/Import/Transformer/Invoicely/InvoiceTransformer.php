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

namespace App\Import\Transformer\Invoicely;

use App\Import\ImportException;
use App\Import\Transformer\BaseTransformer;
use App\Models\Invoice;

/**
 * Class InvoiceTransformer.
 */
class InvoiceTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return bool|array
     */
    public function transform($data)
    {
        if ($this->hasInvoice($data['Details'])) {
            throw new ImportException('Invoice number already exists');
        }

        $transformed = [
            'company_id' => $this->company->id,
            'client_id'  => $this->getClient($this->getString($data, 'Client'), null),
            'number'     => $this->getString($data, 'Details'),
            'date'       => isset($data['Date']) ? $this->parseDate($data['Date']) : null,
            'due_date'   => isset($data['Due']) ? $this->parseDate($data['Due']) : null,
            'status_id'  => Invoice::STATUS_SENT,
            'line_items' => [
                [
                    'cost'     => $amount = $this->getFloat($data, 'Total'),
                    'quantity' => 1,
                ],
            ],
        ];

        if (strtolower($data['Status']) === 'paid') {
            $transformed['payments'] = [
                [
                    'date'   => date('Y-m-d'),
                    'amount' => $amount,
                ],
            ];
        }

        return $transformed;
    }
}
