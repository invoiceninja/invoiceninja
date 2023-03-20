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

namespace App\DataMapper\Tax\de;

use App\DataMapper\Tax\RuleInterface;

class Rule implements RuleInterface
{
    public float $vat_rate = 19;

    public float $vat_threshold = 10000;

    public float $vat_reduced_rate = 7;

    public float $vat_reduced_threshold = 10000;

    public float $at_vat_rate = 20; // Austria
    
    public float $be_vat_rate = 21; // Belgium
    
    public float $bg_vat_rate = 20; // Bulgaria
    
    public float $hr_vat_rate = 25; // Croatia
    
    public float $cy_vat_rate = 19; // Cyprus
    
    public float $cz_vat_rate = 21; // Czech Republic
    
    public float $dk_vat_rate = 25; // Denmark
    
    public float $ee_vat_rate = 20; // Estonia
    
    public float $fi_vat_rate = 24; // Finland
    
    public float $fr_vat_rate = 20; // France
    
    public float $de_vat_rate = 19; // Germany
    
    public float $gr_vat_rate = 24; // Greece
    
    public float $hu_vat_rate = 27; // Hungary
    
    public float $ie_vat_rate = 23; // Ireland
    
    public float $it_vat_rate = 22; // Italy
    
    public float $lv_vat_rate = 21; // Latvia
    
    public float $lt_vat_rate = 21; // Lithuania
    
    public float $lu_vat_rate = 17; // Luxembourg
    
    public float $mt_vat_rate = 18; // Malta
    
    public float $nl_vat_rate = 21; // Netherlands
    
    public float $pl_vat_rate = 23; // Poland
    
    public float $pt_vat_rate = 23; // Portugal
    
    public float $ro_vat_rate = 19; // Romania
    
    public float $sk_vat_rate = 20; // Slovakia
    
    public float $si_vat_rate = 22; // Slovenia
    
    public float $es_vat_rate = 21; // Spain
    
    public float $se_vat_rate = 25; // Sweden
    
    public float $gb_vat_rate = 20; // United Kingdom

    public bool $consumer_tax_exempt = false;

    public bool $business_tax_exempt = true;

    public bool $eu_business_tax_exempt = true;

    public bool $foreign_business_tax_exempt = true;

    public bool $foreign_consumer_tax_exempt = true;

    public function run()
    {
        return $this;
    }
}
