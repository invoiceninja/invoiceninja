<?php
/**
 * client Ninja (https://clientninja.com).
 *
 * @link https://github.com/clientninja/clientninja source repository
 *
 * @copyright Copyright (c) 2022. client Ninja LLC (https://clientninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Import\Transformers\Invoicely;

use App\Import\ImportException;
use App\Import\Transformers\BaseTransformer;
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
            'company_id' => $this->maps['company']->id,
            'client_id'  => $this->getClient($this->getString($data, 'Client'), null),
            'number'     => $this->getString($data, 'Details'),
            'date'       => isset($data['Date']) ? date('Y-m-d', strtotime($data['Date'])) : null,
            'due_date'   => isset($data['Due']) ? date('Y-m-d', strtotime($data['Due'])) : null,
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
