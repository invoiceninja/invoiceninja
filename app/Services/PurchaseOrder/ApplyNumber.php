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

use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Services\AbstractService;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Database\QueryException;

class ApplyNumber extends AbstractService
{
    use GeneratesCounter;

    private $vendor;

    private $purchase_order;

    private $completed = true;

    public function __construct(Vendor $vendor, PurchaseOrder $purchase_order)
    {
        $this->vendor = $vendor;

        $this->purchase_order = $purchase_order;
    }

    public function run()
    {
        if ($this->purchase_order->number != '') {
            return $this->purchase_order;
        }

        switch ($this->client->getSetting('counter_number_applied')) {
            case 'when_saved':
                $this->trySaving();
                break;
            case 'when_sent':
                if ($this->purchase_order->status_id == PurchaseOrder::STATUS_SENT) {
                    $this->trySaving();
                }
                break;

            default:
                break;
        }

        return $this->purchase_order;
    }

    private function trySaving()
    {

        $x=1;

        do{

            try{

                $this->purchase_order->number = $this->getNextPurchaseOrderNumber($this->purchase_order);
                $this->purchase_order->saveQuietly();

                $this->completed = false;
                

            }
            catch(QueryException $e){

                $x++;

                if($x>10)
                    $this->completed = false;
            }
        
        }
        while($this->completed);

    }
}
