<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Bank;

interface BankRevenueInterface
{
    public function transform($transaction);
}
