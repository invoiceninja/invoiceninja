<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */
namespace App\Services\EDocument\Standards\Codes;

class Tax
{
    /** @var array $tax_codes */
    private array $tax_codes = [
        'AE' => [
            'name' => 'Vat Reverse Charge',
            'description' => 'Code specifying that the standard VAT rate is levied from the invoicee.',
        ],
        'E' => [
            'name' => 'Exempt from Tax',
            'description' => 'Code specifying that taxes are not applicable.',
        ],
        'S' => [
            'name' => 'Standard rate',
            'description' => 'Code specifying the standard rate.',
        ],
        'Z' => [
            'name' => 'Zero rated goods',
            'description' => 'Code specifying that the goods are at a zero rate.',
        ],
        'G' => [
            'name' => 'Free export item, VAT not charged',
            'description' => 'Code specifying that the item is free export and taxes are not charged.',
        ],
        'O' => [
            'name' => 'Services outside scope of tax',
            'description' => 'Code specifying that taxes are not applicable to the services.',
        ],
        'K' => [
            'name' => 'VAT exempt for EEA intra-community supply of goods and services',
            'description' => 'A tax category code indicating the item is VAT exempt due to an intra-community supply in the European Economic Area.',
        ],
        'L' => [
            'name' => 'Canary Islands general indirect tax',
            'description' => 'Impuesto General Indirecto Canario (IGIC) is an indirect tax levied on goods and services supplied in the Canary Islands (Spain) by traders and professionals, as well as on import of goods.',
        ],
        'M' => [
            'name' => 'Tax for production, services and importation in Ceuta and Melilla',
            'description' => 'Impuesto sobre la Producción, los Servicios y la Importación (IPSI) is an indirect municipal tax, levied on the production, processing and import of all kinds of movable tangible property, the supply of services and the transfer of immovable property located in the cities of Ceuta and Melilla.',
        ],
        'B' => [
            'name' => 'Transferred (VAT), In Italy',
            'description' => 'VAT not to be paid to the issuer of the invoice but directly to relevant tax authority. This code is allowed in the EN 16931 for Italy only based on the Italian A-deviation.',
        ],
    ];

}