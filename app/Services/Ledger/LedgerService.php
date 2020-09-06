<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Ledger;

use App\Factory\CompanyLedgerFactory;
use App\Models\Activity;
use App\Models\CompanyLedger;

class LedgerService
{
    private $entity;

    public function __construct($entity)
    {
        $this->entity = $entity;
    }

    public function updateInvoiceBalance($adjustment, $notes = '')
    {
        $balance = 0;

        $company_ledger = $this->ledger();

        if ($company_ledger) {
            $balance = $company_ledger->balance;
        }

        $company_ledger = CompanyLedgerFactory::create($this->entity->company_id, $this->entity->user_id);
        $company_ledger->client_id = $this->entity->client_id;
        $company_ledger->adjustment = $adjustment;
        $company_ledger->notes = $notes;
        $company_ledger->balance = $balance + $adjustment;
        $company_ledger->activity_id = Activity::UPDATE_INVOICE;
        $company_ledger->save();

        $this->entity->company_ledger()->save($company_ledger);

        return $this;
    }

    public function updatePaymentBalance($adjustment)
    {
        $balance = 0;

        /* Get the last record for the client and set the current balance*/
        $company_ledger = $this->ledger();

        if ($company_ledger) {
            $balance = $company_ledger->balance;
        }

        $company_ledger = CompanyLedgerFactory::create($this->entity->company_id, $this->entity->user_id);
        $company_ledger->client_id = $this->entity->client_id;
        $company_ledger->adjustment = $adjustment;
        $company_ledger->balance = $balance + $adjustment;
        $company_ledger->activity_id = Activity::UPDATE_PAYMENT;
        $company_ledger->save();

        $this->entity->company_ledger()->save($company_ledger);
    }

    public function updateCreditBalance($adjustment, $notes = '')
    {
        $balance = 0;

        $company_ledger = $this->ledger();

        if ($company_ledger) {
            $balance = $company_ledger->balance;
        }

        $company_ledger = CompanyLedgerFactory::create($this->entity->company_id, $this->entity->user_id);
        $company_ledger->client_id = $this->entity->client_id;
        $company_ledger->adjustment = $adjustment;
        $company_ledger->notes = $notes;
        $company_ledger->balance = $balance + $adjustment;
        $company_ledger->activity_id = Activity::UPDATE_CREDIT;
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
