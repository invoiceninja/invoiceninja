<?php

namespace App\Observers;

use App\Models\ClientGatewayToken;
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
        //when we soft delete a gateway - we also soft delete the tokens
        $company_gateway->client_gateway_tokens()->delete();
    }

    /**
     * Handle the company gateway "restored" event.
     *
     * @param CompanyGateway $company_gateway
     * @return void
     */
    public function restored(CompanyGateway $company_gateway)
    {
        //When we restore the gateway, bring back the tokens!
        ClientGatewayToken::where('company_gateway_id', $company_gateway->id)
                          ->withTrashed()->cursor()->each(function ($cgt) {
                              $cgt->restore();
                          });
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
