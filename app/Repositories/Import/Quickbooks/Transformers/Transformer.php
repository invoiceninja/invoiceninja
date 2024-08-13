<?php

namespace App\Repositories\Import\Quickbooks\Transformers;

use Illuminate\Support\Collection;

class Transformer
{
    public function transform(array $items, string $type): Collection
    {
        if(!method_exists($this, ($method = "transform{$type}s"))) throw new \InvalidArgumentException("Unknown type: $type");

        return call_user_func([$this, $method], $items);
    }

    protected function transformInvoices(array $items): Collection
    {
        return $this->transformation($items, []);
    }

    protected function transformCustomers(array $items): Collection
    {
        return $this->transformation($items, [
            'CompanyName',
            'PrimaryPhone',
            'BillAddr',
            'ShipAddr',
            'Notes',
            'GivenName',
            'FamilyName',
            'PrimaryEmailAddr',
            'CurrencyRef',
            'MetaData'
        ]);
    }

    protected function transformItems(array $items): Collection
    {
        return $this->transformation($items, [
            'Name',
            'Description',
            'PurchaseCost',
            'UnitPrice',
            'QtyOnHand',
            'MetaData'
        ]);
    }

    protected function transformation(array $items, array $keys) : Collection
    {
        return collect($items)->select($keys);
    }

}
