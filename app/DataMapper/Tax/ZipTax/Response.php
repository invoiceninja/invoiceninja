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

namespace App\DataMapper\Tax\ZipTax;

class Response
{
    public string $version = 'v40';

    public int $rCode = 100;

    /**
    *        ["results" => [
    *            [
    *            "geoPostalCode" => "92582",
    *            "geoCity" => "SAN JACINTO",
    *            "geoCounty" => "RIVERSIDE",
    *            "geoState" => "CA",
    *            "taxSales" => 0.0875,
    *            "taxUse" => 0.0875,
    *            "txbService" => "N",
    *            "txbFreight" => "N",
    *            "stateSalesTax" => 0.06,
    *            "stateUseTax" => 0.06,
    *            "citySalesTax" => 0.01,
    *            "cityUseTax" => 0.01,
    *            "cityTaxCode" => "874",
    *            "countySalesTax" => 0.0025,
    *            "countyUseTax" => 0.0025,
    *            "countyTaxCode" => "",
    *            "districtSalesTax" => 0.015,
    *            "districtUseTax" => 0.015,
    *            "district1Code" => "26",
    *            "district1SalesTax" => 0,
    *            "district1UseTax" => 0,
    *            "district2Code" => "26",
    *            "district2SalesTax" => 0.005,
    *            "district2UseTax" => 0.005,
    *            "district3Code" => "",
    *            "district3SalesTax" => 0,
    *            "district3UseTax" => 0,
    *            "district4Code" => "33",
    *            "district4SalesTax" => 0.01,
    *            "district4UseTax" => 0.01,
    *            "district5Code" => "",
    *            "district5SalesTax" => 0,
    *            "district5UseTax" => 0,
    *            "originDestination" => "D",
    *            ],
    *        ]
    *    ];
    *    
    * @var mixed[]
    */
    public array $results = [];

}
    
