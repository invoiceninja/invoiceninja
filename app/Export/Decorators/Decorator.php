<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Export\Decorators;


use App\Models\Task;
use App\Models\Quote;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Vendor;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\RecurringInvoice;

class Decorator {

    public function __invoke(mixed $entity, string $key)
    {
        return match($entity){
            ($entity instanceof Client) => $value = (new ClientDecorator($entity, $key))->transform(),
            ($entity instanceof Payment) => $value = (new PaymentDecorator($entity, $key))->transform(),
            ($entity instanceof Invoice) => $value = (new InvoiceDecorator($entity, $key))->transform(),
            ($entity instanceof RecurringInvoice) => $value = (new RecurringInvoiceDecorator($entity, $key))->transform(),
            ($entity instanceof Credit) => $value = (new CreditDecorator($entity, $key))->transform(),
            ($entity instanceof Quote) => $value = (new QuoteDecorator($entity, $key))->transform(),
            ($entity instanceof Task) => $value = (new TaskDecorator($entity, $key))->transform(),
            ($entity instanceof Expense) => $value = (new ExpenseDecorator($entity, $key))->transform(),
            ($entity instanceof Project) => $value = (new ProjectDecorator($entity, $key))->transform(),
            ($entity instanceof Product) => $value = (new ProductDecorator($entity, $key))->transform(),
            ($entity instanceof Vendor) => $value = (new VendorDecorator($entity, $key))->transform(),
            ($entity instanceof PurchaseOrder) => $value = (new PurchaseOrderDecorator($entity, $key))->transform(),
            default => $value = '',
        };

        return $value;
    }

}
