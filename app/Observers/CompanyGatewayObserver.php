<?php

namespace App\Observers;

use App\Models\CompanyGateway;

class CompanyGatewayObserver
{
    /**
     * Handle the company gateway "created" event.
     *
     * @param CompanyGateway $company_gateway
     * @return void
     */
    public function created(CompanyGateway $company_gateway)
    {

        /* Set company gateway if not exists*/
        if (! $company_gateway->label) {
            $company_gateway->label = $company_gateway->gateway->name;
            $company_gateway->save();
        }
    }

    /**
     * Handle the company gateway "updated" event.
     *
     * @param CompanyGateway $company_gateway
     * @return void
     */
    public function updated(CompanyGateway $company_gateway)
    {
        //
    }

    /**
     * Handle the company gateway "deleted" event.
     *
     * @param CompanyGateway $company_gateway
     * @return void
     */
    public function deleted(CompanyGateway $company_gateway)
    {
        //
    }

    /**
     * Handle the company gateway "restored" event.
     *
     * @param CompanyGateway $company_gateway
     * @return void
     */
    public function restored(CompanyGateway $company_gateway)
    {
        //
    }

    /**
     * Handle the company gateway "force deleted" event.
     *
     * @param CompanyGateway $company_gateway
     * @return void
     */
    public function forceDeleted(CompanyGateway $company_gateway)
    {
        //
    }
}
