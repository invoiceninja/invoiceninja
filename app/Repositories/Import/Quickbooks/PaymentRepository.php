<?php

namespace App\Repositories\Import\Quickbooks;

use App\Repositories\Import\Quickbooks\Contracts\RepositoryInterface as QuickbooksInterface;

class PaymentRepository extends Repository implements QuickbooksInterface
{
    protected string $entity = "Payment";
}
