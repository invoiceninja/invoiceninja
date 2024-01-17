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

class Decorator implements DecoratorInterface
{
    public function __construct()
    {
    }

    public function transform(string $key, mixed $entity): mixed
    {
        $index = $this->getKeyPart(0, $key);
        $column = $this->getKeyPart(1, $key);

        return $this->{$index}()->transform($column, $entity);

    }

    public function invoice(): InvoiceDecorator
    {
        return new InvoiceDecorator();
    }

    public function client(): ClientDecorator
    {
        return new ClientDecorator();
    }

    public function contact(): ContactDecorator
    {
        return new ContactDecorator();
    }

    public function vendor_contact(): VendorContactDecorator
    {
        return new VendorContactDecorator();
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

    public function getKeyPart(int $index, string $key): string
    {
        $parts = explode('.', $key);

        return $parts[$index];
    }
}
