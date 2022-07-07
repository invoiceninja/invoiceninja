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

use App\Factory\ExpenseFactory;
use App\Models\PurchaseOrder;

class PurchaseOrderExpense
{

    private PurchaseOrder $purchase_order;

    public function __construct(PurchaseOrder $purchase_order)
    {
        $this->purchase_order = $purchase_order;
    }

    public function run()
    {

        $expense = ExpenseFactory::create($this->purchase_order->company_id, $this->purchase_order->user_id);

        $expense->amount = $this->purchase_order->uses_inclusive_taxes ? $this->purchase_order->amount : ($this->purchase_order->amount - $this->purchase_order->total_taxes);

        $expense->date = now();
        $expense->vendor_id = $this->purchase_order->vendor_id;
        $expense->public_notes = $this->purchase_order->public_notes;
        $expense->uses_inclusive_taxes = $this->purchase_order->uses_inclusive_taxes;
        $expense->calculate_tax_by_amount = true;

        $tax_map = $this->purchase_order->calc()->getTaxMap();

        $expense->tax_amount1 = $this->purchase_order->total_taxes;
        $expense->tax_name1 = ctrans("texts.tax");

        $expense->save();

        $this->purchase_order->expense_id = $expense->id;
        $this->purchase_order->save();

        return $expense;

    }
}
