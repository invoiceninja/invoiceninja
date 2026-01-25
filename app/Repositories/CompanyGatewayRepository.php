<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Repositories;

use App\Utils\Ninja;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Repositories\BaseRepository;

/**
 * CompanyGatewayRepository.
 */
class CompanyGatewayRepository extends BaseRepository
{
    public function __construct()
    {
    }

    public function archive($company_gateway): CompanyGateway
    {
        
        parent::archive($company_gateway);
        
        $this->removeGatewayFromCompanyGatewayIds($company_gateway);
        
        return $company_gateway;
    }

    public function delete($company_gateway): CompanyGateway
    {
        parent::delete($company_gateway);

        $this->removeGatewayFromCompanyGatewayIds($company_gateway);

        return $company_gateway;
    }

    public function restore($company_gateway): CompanyGateway
    {
        parent::restore($company_gateway);

        $this->addGatewayToCompanyGatewayIds($company_gateway);

        return $company_gateway;
    }

    public function addGatewayToCompanyGatewayIds(CompanyGateway $company_gateway)
    {
        $company_gateway_ids = $company_gateway->company->getSetting('company_gateway_ids');

        if(strlen($company_gateway_ids ?? '') > 2){
            $transformed_ids = collect($this->transformKeys(explode(',', $company_gateway_ids)))
                                ->push($company_gateway->hashed_id)
                                ->implode(",");

            $company = $company_gateway->company;
            $settings = $company->settings;
            $settings->company_gateway_ids = $transformed_ids;
            $company->settings = $settings;
            $company->save();
        }

    }

    public function removeGatewayFromCompanyGatewayIds(CompanyGateway $company_gateway)
    {
        $company_gateway_ids = $company_gateway->company->getSetting('company_gateway_ids');

        if(strpos($company_gateway_ids, $company_gateway->hashed_id) !== false){
            $transformed_ids = collect($this->transformKeys(explode(',', $company_gateway_ids)))
                                ->filter(function ($id) use ($company_gateway){
                                    return $id !== $company_gateway->hashed_id;
                                })
                                ->implode(",");

            $company = $company_gateway->company;
            $settings = $company->settings;
            $settings->company_gateway_ids = $transformed_ids;
            $company->settings = $settings;
            $company->save();
        }

    }
}