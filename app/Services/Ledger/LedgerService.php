<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Ledger;

use App\Factory\CompanyLedgerFactory;
use App\Models\CompanyLedger;

class LedgerService
{

    private $entity;

    public function __construct($entity)
    {
        $this->entity = $entity;
    }

    public function updateInvoiceBalance($adjustment)
    {
        $balance = 0;

        if ($this->ledger()) {
            $balance = $this->ledger()->balance;
        }

        $adjustment = $balance + $adjustment;
        
        $company_ledger = CompanyLedgerFactory::create($this->entity->company_id, $this->entity->user_id);
        $company_ledger->client_id = $this->entity->client_id;
        $company_ledger->adjustment = $adjustment;
        $company_ledger->balance = $balance + $adjustment;
        $company_ledger->save();

        $this->entity->company_ledger()->save($company_ledger);

        return $this;
    }

    private function ledger() :?CompanyLedger
    {

        return CompanyLedger::whereClientId($this->entity->client_id)
                        ->whereCompanyId($this->entity->company_id)
                        ->orderBy('id', 'DESC')
                        ->first();

    }

    public function save()
    {

        $this->entity->save();

        return $this->entity;

    }

}
