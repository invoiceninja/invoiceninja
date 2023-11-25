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
use App\Export\Decorators\DecoratorInterface;

class Decorator implements DecoratorInterface{

    public $entity;

    public function __construct()
    {
    }

    public function transform(): string
    {
        return 'Decorator';
    }

    public function invoice(): InvoiceDecorator
    {
        return new InvoiceDecorator();
    }

    public function client(): ClientDecorator
    {
        return new ClientDecorator();
    }

    public function payment(): PaymentDecorator
    {
        return new PaymentDecorator();
    }

    public function credit(): CreditDecorator
    {
        return new CreditDecorator();
    }

    public function vendor(): VendorDecorator
    {
        return new VendorDecorator();
    }

    public function expense(): ExpenseDecorator
    {
        return new ExpenseDecorator();
    }

    public function product(): ProductDecorator
    {
        return new ProductDecorator();
    }

    public function project(): ProjectDecorator
    {
        return new ProjectDecorator();
    }

    public function task(): TaskDecorator
    {
        return new TaskDecorator();
    }

    public function quote(): QuoteDecorator
    {
        return new QuoteDecorator();
    }

    public function recurring_invoice(): RecurringInvoiceDecorator
    {
        return new RecurringInvoiceDecorator();
    }

    public function purchase_order(): PurchaseOrderDecorator
    {
        return new PurchaseOrderDecorator();
    }

    public function setEntity($entity): self
    {
        $this->entity = $entity;

        return $this;
    }

    public function getEntity(): mixed
    {
        return $this->entity;
    }
}
