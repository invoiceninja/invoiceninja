<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Invoice;

use App\Services\Client\ClientService;

class InvoiceService
{
    private $invoice;

    private $client_service;

    public function __construct($invoice)
    {
        $this->invoice = $invoice;
        
        $this->client_service = new ClientService();
    }


}
