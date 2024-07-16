<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Integration\Einvoice\Storecove;

use Tests\TestCase;
use Tests\MockAccountData;
use Illuminate\Foundation\Testing\DatabaseTransactions;


class StorecoveTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        if (config('ninja.testvars.travis') !== false || !config('ninja.storecove_api_key')) 
            $this->markTestSkipped("do not run in CI");
    }

    public function testCreateLegalEntity()
    {

        $data = [
            'acts_as_receiver' => true,
            'acts_as_sender' => true,
            'advertisements' => ['invoice'],
            'city' => $this->company->settings->city,
            'country' => 'DE',
            'county' => $this->company->settings->state,
            'line1' => $this->company->settings->address1,
            'line2' => $this->company->settings->address2,
            'party_name' => $this->company->present()->name(),
            'tax_registered' => true,
            'tenant_id' => $this->company->company_key,
            'zip' => $this->company->settings->postal_code,
            'peppol_identifiers' => [
                'scheme' => 'DE:VAT',
                'id' => 'DE:VAT'
            ],
        ];

        $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
        $r = $sc->createLegalEntity($data, $this->company);

        $this->assertIsArray($r);

    }

    // public function testUpdateLegalEntity()
    // {
    //     $data = [
    //         'peppol_identifiers' => [
    //             'scheme' => 'DE:VAT',
    //             'id' => 'DE:VAT'
    //         ],
    //     ];

    //     $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
    //     $r = $sc->updateLegalEntity(290868, $data);

    //     $this->assertIsArray($r);
    //     nlog($r);

    // }

    public function testGetLegalEntity()
    {

        
        $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
        $r = $sc->getLegalEntity(290868);

        $this->assertIsArray($r);

        nlog($r);

    }    

    public function testSendDocument()
    {

        $x = '<?xml version="1.0"?>
            <cbc:ID>0061</cbc:ID>
            <cbc:IssueDate>2024-07-15</cbc:IssueDate>
            <cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>
            <cac:AccountingSupplierParty>
            <cac:Party>
                <cac:PartyName>
                <cbc:Name>Eladio Ullrich I</cbc:Name>
                </cac:PartyName>
                <cac:PostalAddress>
                <cbc:StreetName>Jasper Brook</cbc:StreetName>
                <cbc:CityName>Kodychester</cbc:CityName>
                <cbc:PostalZone>73445-5131</cbc:PostalZone>
                <cbc:CountrySubentity>South Dakota</cbc:CountrySubentity>
                <cac:Country>
                    <cbc:IdentificationCode>AT</cbc:IdentificationCode>
                </cac:Country>
                </cac:PostalAddress>
                <cac:PhysicalLocation>
                <cbc:StreetName>Jasper Brook</cbc:StreetName>
                <cbc:CityName>Kodychester</cbc:CityName>
                <cbc:PostalZone>73445-5131</cbc:PostalZone>
                <cbc:CountrySubentity>South Dakota</cbc:CountrySubentity>
                <cac:Country>
                    <cbc:IdentificationCode>AT</cbc:IdentificationCode>
                </cac:Country>
                </cac:PhysicalLocation>
                <cac:Contact>
                <cbc:ElectronicMail>small@example.com</cbc:ElectronicMail>
                </cac:Contact>
            </cac:Party>
            </cac:AccountingSupplierParty>
            <cac:AccountingCustomerParty>
            <cac:Party>
                <cac:PartyName>
                <cbc:Name>Beispiel GmbH</cbc:Name>
                </cac:PartyName>
                <cac:PostalAddress>
                <cbc:StreetName>45 Hauptstra&#xDF;e</cbc:StreetName>
                <cbc:CityName>Berlin</cbc:CityName>
                <cbc:PostalZone>10115</cbc:PostalZone>
                <cbc:CountrySubentity>Berlin</cbc:CountrySubentity>
                <cac:Country>
                    <cbc:IdentificationCode>DE</cbc:IdentificationCode>
                </cac:Country>
                </cac:PostalAddress>
                <cac:PhysicalLocation>
                <cbc:StreetName>45 Hauptstra&#xDF;e</cbc:StreetName>
                <cbc:CityName>Berlin</cbc:CityName>
                <cbc:PostalZone>10115</cbc:PostalZone>
                <cbc:CountrySubentity>Berlin</cbc:CountrySubentity>
                <cac:Country>
                    <cbc:IdentificationCode>DE</cbc:IdentificationCode>
                </cac:Country>
                </cac:PhysicalLocation>
                <cac:Contact>
                <cbc:ElectronicMail>TTKGjKW9Rv00LEr@example.com</cbc:ElectronicMail>
                </cac:Contact>
            </cac:Party>
            </cac:AccountingCustomerParty>
            <cac:TaxTotal/>
            <cac:LegalMonetaryTotal>
            <cbc:LineExtensionAmount currencyID="EUR">215</cbc:LineExtensionAmount>
            <cbc:TaxExclusiveAmount currencyID="EUR">215</cbc:TaxExclusiveAmount>
            <cbc:TaxInclusiveAmount currencyID="EUR">215.00</cbc:TaxInclusiveAmount>
            <cbc:PayableAmount currencyID="EUR">215.00</cbc:PayableAmount>
            </cac:LegalMonetaryTotal>
            <cac:InvoiceLine>
            <cbc:ID>1</cbc:ID>
            <cbc:InvoicedQuantity>1</cbc:InvoicedQuantity>
            <cbc:LineExtensionAmount currencyID="EUR">10</cbc:LineExtensionAmount>
            <cac:TaxTotal>
                <cbc:TaxAmount currencyID="EUR">0.5</cbc:TaxAmount>
                <cac:TaxSubtotal>
                <cbc:TaxableAmount currencyID="EUR">10</cbc:TaxableAmount>
                <cbc:TaxAmount currencyID="EUR">0.5</cbc:TaxAmount>
                <cac:TaxCategory>
                    <cbc:ID>C62</cbc:ID>
                    <cbc:Percent>20</cbc:Percent>
                    <cac:TaxScheme>
                    <cbc:ID>USt</cbc:ID>
                    </cac:TaxScheme>
                </cac:TaxCategory>
                </cac:TaxSubtotal>
            </cac:TaxTotal>
            <cac:Item>
                <cbc:Description>The Pro Plan NO MORE</cbc:Description>
                <cbc:Name>ee</cbc:Name>
            </cac:Item>
            <cac:Price>
                <cbc:PriceAmount currencyID="EUR">10</cbc:PriceAmount>
            </cac:Price>
            </cac:InvoiceLine>
            <cac:InvoiceLine>
            <cbc:ID>2</cbc:ID>
            <cbc:InvoicedQuantity>1</cbc:InvoicedQuantity>
            <cbc:LineExtensionAmount currencyID="EUR">14</cbc:LineExtensionAmount>
            <cac:Item>
                <cbc:Description>The Enterprise Plan</cbc:Description>
                <cbc:Name>eee</cbc:Name>
            </cac:Item>
            <cac:Price>
                <cbc:PriceAmount currencyID="EUR">14</cbc:PriceAmount>
            </cac:Price>
            </cac:InvoiceLine>
            <cac:InvoiceLine>
            <cbc:ID>3</cbc:ID>
            <cbc:InvoicedQuantity>1</cbc:InvoicedQuantity>
            <cbc:LineExtensionAmount currencyID="EUR">191</cbc:LineExtensionAmount>
            <cac:Item>
                <cbc:Description>Soluta provident.</cbc:Description>
                <cbc:Name>k</cbc:Name>
            </cac:Item>
            <cac:Price>
                <cbc:PriceAmount currencyID="EUR">191</cbc:PriceAmount>
            </cac:Price>
            </cac:InvoiceLine>';

        
        $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
        $sc->sendDocument($x);

    }

}
