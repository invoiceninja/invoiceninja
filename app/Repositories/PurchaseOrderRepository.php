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

namespace App\Repositories;


use App\Models\PurchaseOrder;
use App\Utils\Traits\MakesHash;

class PurchaseOrderRepository extends BaseRepository
{
    use MakesHash;

    public function __construct()
    {
    }

    public function save(array $data, PurchaseOrder $purchase_order) : ?PurchaseOrder
    {
        $purchase_order->fill($data);
        $purchase_order->save();

        return $purchase_order;
    }

}
