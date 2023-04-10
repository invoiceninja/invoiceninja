<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\DataMapper\Tax;

class TaxModel 
{

    public string $seller_subregion = 'CA';

    public string $version = 'alpha';

    public object $regions;

    public function __construct(public ?TaxModel $model = null)
    {
        
        if(!$this->model) 
            $this->regions = $this->init();
        else
            $this->regions = $model;

    }

    public function init()
    {
        $this->regions = new \stdClass();
        $this->regions->US = new \stdClass();
        $this->regions->EU = new \stdClass();

        $this->usRegion()
             ->euRegion()
             ->auRegion();


        return $this->regions;
    }

    private function auRegion(): self
    {
        $this->regions->AU = new \stdClass();
        $this->regions->AU->has_sales_above_threshold = false;
        $this->regions->AU->tax_all_subregions = false;
        $this->regions->AU->vat_threshold = 75000;
        $this->auSubRegions();

        return $this;
    }

    private function auSubRegions(): self 
    {

        $this->regions->AU->subregions = new \stdClass();
        $this->regions->AU->subregions->AU = new \stdClass();
        $this->regions->AU->subregions->AU->apply_tax = false;
        $this->regions->AU->subregions->AU->tax_rate = 10;

        return $this;
    }

    private function usRegion(): self
    {
        $this->regions->US->has_sales_above_threshold = false;
        $this->regions->US->tax_all_subregions = false;
        $this->usSubRegions();

        return $this;
    }

    private function euRegion(): self
    {
     
        $this->regions->EU->has_sales_above_threshold = false;
        $this->regions->EU->tax_all_subregions = false;
        $this->regions->EU->vat_threshold = 10000;
        $this->euSubRegions();

        return $this;
    }

    private function usSubRegions(): self
    {
        $this->regions->US->subregions = new \stdClass();
        $this->regions->US->subregions->AL = new \stdClass();
        $this->regions->US->subregions->AL->apply_tax = false;
        $this->regions->US->subregions->AK = new \stdClass();
        $this->regions->US->subregions->AK->apply_tax = false;
        $this->regions->US->subregions->AZ = new \stdClass();
        $this->regions->US->subregions->AZ->apply_tax = false;
        $this->regions->US->subregions->AR = new \stdClass();
        $this->regions->US->subregions->AR->apply_tax = false;
        $this->regions->US->subregions->CA = new \stdClass();
        $this->regions->US->subregions->CA->apply_tax = false;
        $this->regions->US->subregions->CO = new \stdClass();
        $this->regions->US->subregions->CO->apply_tax = false;
        $this->regions->US->subregions->CT = new \stdClass();
        $this->regions->US->subregions->CT->apply_tax = false;
        $this->regions->US->subregions->DE = new \stdClass();
        $this->regions->US->subregions->DE->apply_tax = false;
        $this->regions->US->subregions->FL = new \stdClass();
        $this->regions->US->subregions->FL->apply_tax = false;
        $this->regions->US->subregions->GA = new \stdClass();
        $this->regions->US->subregions->GA->apply_tax = false;
        $this->regions->US->subregions->HI = new \stdClass();
        $this->regions->US->subregions->HI->apply_tax = false;
        $this->regions->US->subregions->ID = new \stdClass();
        $this->regions->US->subregions->ID->apply_tax = false;
        $this->regions->US->subregions->IL = new \stdClass();
        $this->regions->US->subregions->IL->apply_tax = false;
        $this->regions->US->subregions->IN = new \stdClass();
        $this->regions->US->subregions->IN->apply_tax = false;
        $this->regions->US->subregions->IA = new \stdClass();
        $this->regions->US->subregions->IA->apply_tax = false;
        $this->regions->US->subregions->KS = new \stdClass();
        $this->regions->US->subregions->KS->apply_tax = false;
        $this->regions->US->subregions->KY = new \stdClass();
        $this->regions->US->subregions->KY->apply_tax = false;
        $this->regions->US->subregions->LA = new \stdClass();
        $this->regions->US->subregions->LA->apply_tax = false;
        $this->regions->US->subregions->ME = new \stdClass();
        $this->regions->US->subregions->ME->apply_tax = false;
        $this->regions->US->subregions->MD = new \stdClass();
        $this->regions->US->subregions->MD->apply_tax = false;
        $this->regions->US->subregions->MA = new \stdClass();
        $this->regions->US->subregions->MA->apply_tax = false;
        $this->regions->US->subregions->MI = new \stdClass();
        $this->regions->US->subregions->MI->apply_tax = false;
        $this->regions->US->subregions->MN = new \stdClass();
        $this->regions->US->subregions->MN->apply_tax = false;
        $this->regions->US->subregions->MS = new \stdClass();
        $this->regions->US->subregions->MS->apply_tax = false;
        $this->regions->US->subregions->MO = new \stdClass();
        $this->regions->US->subregions->MO->apply_tax = false;
        $this->regions->US->subregions->MT = new \stdClass();
        $this->regions->US->subregions->MT->apply_tax = false;
        $this->regions->US->subregions->NE = new \stdClass();
        $this->regions->US->subregions->NE->apply_tax = false;
        $this->regions->US->subregions->NV = new \stdClass();
        $this->regions->US->subregions->NV->apply_tax = false;
        $this->regions->US->subregions->NH = new \stdClass();
        $this->regions->US->subregions->NH->apply_tax = false;
        $this->regions->US->subregions->NJ = new \stdClass();
        $this->regions->US->subregions->NJ->apply_tax = false;
        $this->regions->US->subregions->NM = new \stdClass();
        $this->regions->US->subregions->NM->apply_tax = false;
        $this->regions->US->subregions->NY = new \stdClass();
        $this->regions->US->subregions->NY->apply_tax = false;
        $this->regions->US->subregions->NC = new \stdClass();
        $this->regions->US->subregions->NC->apply_tax = false;
        $this->regions->US->subregions->ND = new \stdClass();
        $this->regions->US->subregions->ND->apply_tax = false;
        $this->regions->US->subregions->OH = new \stdClass();
        $this->regions->US->subregions->OH->apply_tax = false;
        $this->regions->US->subregions->OK = new \stdClass();
        $this->regions->US->subregions->OK->apply_tax = false;
        $this->regions->US->subregions->OR = new \stdClass();
        $this->regions->US->subregions->OR->apply_tax = false;
        $this->regions->US->subregions->PA = new \stdClass();
        $this->regions->US->subregions->PA->apply_tax = false;
        $this->regions->US->subregions->RI = new \stdClass();
        $this->regions->US->subregions->RI->apply_tax = false;
        $this->regions->US->subregions->SC = new \stdClass();
        $this->regions->US->subregions->SC->apply_tax = false;
        $this->regions->US->subregions->SD = new \stdClass();
        $this->regions->US->subregions->SD->apply_tax = false;
        $this->regions->US->subregions->TN = new \stdClass();
        $this->regions->US->subregions->TN->apply_tax = false;
        $this->regions->US->subregions->TX = new \stdClass();
        $this->regions->US->subregions->TX->apply_tax = false;
        $this->regions->US->subregions->UT = new \stdClass();
        $this->regions->US->subregions->UT->apply_tax = false;
        $this->regions->US->subregions->VT = new \stdClass();
        $this->regions->US->subregions->VT->apply_tax = false;
        $this->regions->US->subregions->VA = new \stdClass();
        $this->regions->US->subregions->VA->apply_tax = false;
        $this->regions->US->subregions->WA = new \stdClass();
        $this->regions->US->subregions->WA->apply_tax = false;
        $this->regions->US->subregions->WV = new \stdClass();
        $this->regions->US->subregions->WV->apply_tax = false;
        $this->regions->US->subregions->WI = new \stdClass();
        $this->regions->US->subregions->WI->apply_tax = false;
        $this->regions->US->subregions->WY = new \stdClass();
        $this->regions->US->subregions->WY->apply_tax = false;

        return $this;
    }

    private function euSubRegions(): self
    {
        
        $this->regions->EU->subregions = new \stdClass();

        $this->regions->EU->subregions->AT = new \stdClass();
        $this->regions->EU->subregions->AT->tax_rate = 21;
        $this->regions->EU->subregions->AT->reduced_tax_rate = 11;
        $this->regions->EU->subregions->AT->apply_tax = false;

        $this->regions->EU->subregions->BE = new \stdClass();
        $this->regions->EU->subregions->BE->tax_rate = 21;
        $this->regions->EU->subregions->BE->reduced_tax_rate = 6;
        $this->regions->EU->subregions->BE->apply_tax = false;

        $this->regions->EU->subregions->BG = new \stdClass();
        $this->regions->EU->subregions->BG->tax_rate = 20;
        $this->regions->EU->subregions->BG->reduced_tax_rate = 9;
        $this->regions->EU->subregions->BG->apply_tax = false;

        $this->regions->EU->subregions->CY = new \stdClass();
        $this->regions->EU->subregions->CY->tax_rate = 19;
        $this->regions->EU->subregions->CY->reduced_tax_rate = 9;
        $this->regions->EU->subregions->CY->apply_tax = false;

        $this->regions->EU->subregions->CZ = new \stdClass();
        $this->regions->EU->subregions->CZ->tax_rate = 21;
        $this->regions->EU->subregions->CZ->reduced_tax_rate = 15;
        $this->regions->EU->subregions->CZ->apply_tax = false;

        $this->regions->EU->subregions->DE = new \stdClass();
        $this->regions->EU->subregions->DE->tax_rate = 19;
        $this->regions->EU->subregions->DE->reduced_tax_rate = 7;
        $this->regions->EU->subregions->DE->apply_tax = false;

        $this->regions->EU->subregions->DK = new \stdClass();
        $this->regions->EU->subregions->DK->tax_rate = 25;
        $this->regions->EU->subregions->DK->reduced_tax_rate = 0;
        $this->regions->EU->subregions->DK->apply_tax = false;

        $this->regions->EU->subregions->EE = new \stdClass();
        $this->regions->EU->subregions->EE->tax_rate = 20;
        $this->regions->EU->subregions->EE->reduced_tax_rate = 9;
        $this->regions->EU->subregions->EE->apply_tax = false;

        $this->regions->EU->subregions->ES = new \stdClass();
        $this->regions->EU->subregions->ES->tax_rate = 21;
        $this->regions->EU->subregions->ES->reduced_tax_rate = 10;
        $this->regions->EU->subregions->ES->apply_tax = false;

        $this->regions->EU->subregions->FI = new \stdClass();
        $this->regions->EU->subregions->FI->tax_rate = 24;
        $this->regions->EU->subregions->FI->reduced_tax_rate = 14;
        $this->regions->EU->subregions->FI->apply_tax = false;

        $this->regions->EU->subregions->FR = new \stdClass();
        $this->regions->EU->subregions->FR->tax_rate = 20;
        $this->regions->EU->subregions->FR->reduced_tax_rate = 5.5;
        $this->regions->EU->subregions->FR->apply_tax = false;

        // $this->regions->EU->subregions->GB = new \stdClass();
        // $this->regions->EU->subregions->GB->tax_rate = 20;
        // $this->regions->EU->subregions->GB->reduced_tax_rate = 0;
        // $this->regions->EU->subregions->GB->apply_tax = false;

        $this->regions->EU->subregions->GR = new \stdClass();
        $this->regions->EU->subregions->GR->tax_rate = 24;
        $this->regions->EU->subregions->GR->reduced_tax_rate = 13;
        $this->regions->EU->subregions->GR->apply_tax = false;

        $this->regions->EU->subregions->HR = new \stdClass();
        $this->regions->EU->subregions->HR->tax_rate = 25;
        $this->regions->EU->subregions->HR->reduced_tax_rate = 5;
        $this->regions->EU->subregions->HR->apply_tax = false;

        $this->regions->EU->subregions->HU = new \stdClass();
        $this->regions->EU->subregions->HU->tax_rate = 27;
        $this->regions->EU->subregions->HU->reduced_tax_rate = 5;
        $this->regions->EU->subregions->HU->apply_tax = false;

        $this->regions->EU->subregions->IE = new \stdClass();
        $this->regions->EU->subregions->IE->tax_rate = 23;
        $this->regions->EU->subregions->IE->reduced_tax_rate = 0;
        $this->regions->EU->subregions->IE->apply_tax = false;

        $this->regions->EU->subregions->IT = new \stdClass();
        $this->regions->EU->subregions->IT->tax_rate = 22;
        $this->regions->EU->subregions->IT->reduced_tax_rate = 10;
        $this->regions->EU->subregions->IT->apply_tax = false;

        $this->regions->EU->subregions->LT = new \stdClass();
        $this->regions->EU->subregions->LT->tax_rate = 21;
        $this->regions->EU->subregions->LT->reduced_tax_rate = 9;
        $this->regions->EU->subregions->LT->apply_tax = false;

        $this->regions->EU->subregions->LU = new \stdClass();
        $this->regions->EU->subregions->LU->tax_rate = 17;
        $this->regions->EU->subregions->LU->reduced_tax_rate = 3;
        $this->regions->EU->subregions->LU->apply_tax = false;

        $this->regions->EU->subregions->LV = new \stdClass();
        $this->regions->EU->subregions->LV->tax_rate = 21;
        $this->regions->EU->subregions->LV->reduced_tax_rate = 12;
        $this->regions->EU->subregions->LV->apply_tax = false;

        $this->regions->EU->subregions->MT = new \stdClass();
        $this->regions->EU->subregions->MT->tax_rate = 18;
        $this->regions->EU->subregions->MT->reduced_tax_rate = 5;
        $this->regions->EU->subregions->MT->apply_tax = false;

        $this->regions->EU->subregions->NL = new \stdClass();
        $this->regions->EU->subregions->NL->tax_rate = 21;
        $this->regions->EU->subregions->NL->reduced_tax_rate = 9;
        $this->regions->EU->subregions->NL->apply_tax = false;

        $this->regions->EU->subregions->PT = new \stdClass();
        $this->regions->EU->subregions->PT->tax_rate = 23;
        $this->regions->EU->subregions->PT->reduced_tax_rate = 6;
        $this->regions->EU->subregions->PT->apply_tax = false;

        $this->regions->EU->subregions->RO = new \stdClass();
        $this->regions->EU->subregions->RO->tax_rate = 19;
        $this->regions->EU->subregions->RO->reduced_tax_rate = 5;
        $this->regions->EU->subregions->RO->apply_tax = false;

        $this->regions->EU->subregions->SE = new \stdClass();
        $this->regions->EU->subregions->SE->tax_rate = 25;
        $this->regions->EU->subregions->SE->reduced_tax_rate = 12;
        $this->regions->EU->subregions->SE->apply_tax = false;

        $this->regions->EU->subregions->SI = new \stdClass();
        $this->regions->EU->subregions->SI->tax_rate = 22;
        $this->regions->EU->subregions->SI->reduced_tax_rate = 9.5;
        $this->regions->EU->subregions->SI->apply_tax = false;

        $this->regions->EU->subregions->SK = new \stdClass();
        $this->regions->EU->subregions->SK->tax_rate = 20;
        $this->regions->EU->subregions->SK->reduced_tax_rate = 10;
        $this->regions->EU->subregions->SK->apply_tax = false;

        return $this;

    }

}
