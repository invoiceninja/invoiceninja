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

namespace App\DataMapper\Transactions;

use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TransactionEvent;

/**
 * InvoiceReversalTransaction.
 */
class InvoiceReversalTransaction extends BaseTransaction implements TransactionInterface
{
    public $event_id = TransactionEvent::INVOICE_REVERSED;
}
