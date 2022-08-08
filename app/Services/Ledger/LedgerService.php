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

namespace App\Services\Ledger;

use App\Factory\CompanyLedgerFactory;
use App\Jobs\Ledger\ClientLedgerBalanceUpdate;
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
        $company_ledger = CompanyLedgerFactory::create($this->entity->company_id, $this->entity->user_id);
        $company_ledger->client_id = $this->entity->client_id;
        $company_ledger->adjustment = $adjustment;
        $company_ledger->notes = $notes;
        $company_ledger->activity_id = Activity::UPDATE_INVOICE;
        $company_ledger->save();

        $this->entity->company_ledger()->save($company_ledger);

        ClientLedgerBalanceUpdate::dispatch($this->entity->company, $this->entity->client)->delay(now()->addSeconds(300));

        return $this;
    }

    public function updatePaymentBalance($adjustment, $notes = '')
    {
        $company_ledger = CompanyLedgerFactory::create($this->entity->company_id, $this->entity->user_id);
        $company_ledger->client_id = $this->entity->client_id;
        $company_ledger->adjustment = $adjustment;
        $company_ledger->activity_id = Activity::UPDATE_PAYMENT;
        $company_ledger->notes = $notes;
        $company_ledger->save();

        $this->entity->company_ledger()->save($company_ledger);

        ClientLedgerBalanceUpdate::dispatch($this->entity->company, $this->entity->client)->delay(now()->addSeconds(300));

        return $this;
    }

    public function updateCreditBalance($adjustment, $notes = '')
    {
        $company_ledger = CompanyLedgerFactory::create($this->entity->company_id, $this->entity->user_id);
        $company_ledger->client_id = $this->entity->client_id;
        $company_ledger->adjustment = $adjustment;
        $company_ledger->notes = $notes;
        $company_ledger->activity_id = Activity::UPDATE_CREDIT;
        $company_ledger->save();

        $this->entity->company_ledger()->save($company_ledger);

        ClientLedgerBalanceUpdate::dispatch($this->entity->company, $this->entity->client)->delay(now()->addSeconds(300));

        return $this;
    }

    public function save()
    {
        $this->entity->save();

        return $this->entity;
    }
}
