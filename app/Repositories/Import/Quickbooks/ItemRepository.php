<?php

namespace App\Repositories\Import\Quickbooks;

use App\Repositories\Import\Quickbooks\Repository;
use App\Repositories\Import\Quickbooks\Contracts\RepositoryInterface;

class ItemRepository extends Repository implements RepositoryInterface
{
    protected string $entity = "Item";
}
