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

namespace App\Services\Bank;

use App\Models\Company;

class BankService
{

    public Company $company;

    private $invoices;

    public function __construct(Company $company)
    {
        $this->company = $company;

        $this->invoices = $this->company->invoices()->whereIn('status_id', [1,2,3])
                                            ->where('is_deleted', 0)
                                            ->get();

    }

    public function match($transactions): array
    {

        foreach($transactions as $transaction)
        {
            $this->matchIncome($transaction);
        }

        return $transactions;
    }

    private function matchExpense()
    {

    }

    private function matchIncome($transaction)
    {
        $description = str_replace(" ", "", $transaction->description);

        $invoice = $this->invoices->first(function ($value, $key) use ($description){

            return str_contains($value->number, $description);
            
        });

        if($invoice)
            $transaction['invocie_id'] = $invoice->hashed_id;

        return $transaction;
    }

}
