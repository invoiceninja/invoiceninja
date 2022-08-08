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

namespace App\Services\PurchaseOrder;

use App\Events\Invoice\InvoiceWasEmailed;
use App\Events\PurchaseOrder\PurchaseOrderWasEmailed;
use App\Jobs\Entity\EmailEntity;
use App\Jobs\PurchaseOrder\PurchaseOrderEmail;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Services\AbstractService;
use App\Utils\Ninja;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Http\Request;

class TriggeredActions extends AbstractService
{
    use GeneratesCounter;

    private $request;

    private $purchase_order;

    public function __construct(PurchaseOrder $purchase_order, Request $request)
    {
        $this->request = $request;

        $this->purchase_order = $purchase_order;
    }

    public function run()
    {
        if ($this->request->has('send_email') && $this->request->input('send_email') == 'true') {
            $this->purchase_order->service()->markSent()->touchPdf()->save();
            $this->sendEmail();
        }

        if ($this->request->has('mark_sent') && $this->request->input('mark_sent') == 'true') {
            $this->purchase_order = $this->purchase_order->service()->markSent()->touchPdf()->save();
        }

        // if ($this->request->has('cancel') && $this->request->input('cancel') == 'true') {
        //     $this->purchase_order = $this->purchase_order->service()->handleCancellation()->save();
        // }

        return $this->purchase_order;
    }

    private function sendEmail()
    {
        PurchaseOrderEmail::dispatch($this->purchase_order, $this->purchase_order->company);
    }
}
