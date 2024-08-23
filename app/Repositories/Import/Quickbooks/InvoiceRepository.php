<?php

namespace App\Repositories\Import\Quickbooks;

use App\Repositories\Import\Quickbooks\Contracts\RepositoryInterface as QuickbooksInterface;

class InvoiceRepository extends Repository implements QuickbooksInterface
{
    protected string $entity = "Invoice";
}
