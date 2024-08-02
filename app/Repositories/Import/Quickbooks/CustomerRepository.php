<?php

namespace App\Repositories\Import\QuickBooks;

use App\Repositories\Import\Quickbooks\Repository;
use App\Repositories\Import\Quickbooks\Contracts\RepositoryInterface;

class CustomerRepository extends Repository implements RepositoryInterface
{
    protected string $entity = "Customer";
}
