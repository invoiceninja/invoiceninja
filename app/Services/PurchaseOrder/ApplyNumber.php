<?php


namespace App\Services\PurchaseOrder;


use App\Models\Client;
use App\Models\Credit;
use App\Models\PurchaseOrder;
use App\Services\AbstractService;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Database\QueryException;

class ApplyNumber extends AbstractService
{
    use GeneratesCounter;

    private Client $client;

    private PurchaseOrder $purchase_order;

    private bool $completed = true;

    public function __construct(Client $client, PurchaseOrder $purchase_order)
    {
        $this->client = $client;

        $this->purchase_order = $purchase_order;
    }

    public function run()
    {
        if ($this->purchase_order->number != '') {
            return $this->purchase_order;
        }

        $this->trySaving();

        return $this->purchase_order;
    }
    private function trySaving()
    {
        $x=1;
        do{
            try{
                $this->purchase_order->number = $this->getNextPurchaseOrderNumber($this->client, $this->purchase_order);
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
