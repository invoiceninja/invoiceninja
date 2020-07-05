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

use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AbstractService;
use App\Services\Client\ClientService;
use App\Services\Payment\PaymentService;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Http\Request;

class TriggeredActions extends AbstractService
{
    use GeneratesCounter;

    private $request;

    private $invoice;

    public function __construct(Invoice $invoice, Request $request)
    {
        $this->request = $request;
        
        $this->invoice = $invoice;
    }

    public function run()
    {
        //the request may have buried in it additional actions we should automatically perform on the invoice
    }
}
