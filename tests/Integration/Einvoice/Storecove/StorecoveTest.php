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

    // public function testCreateLegalEntity()
    // {

    //     $data = [
    //         'acts_as_receiver' => true,
    //         'acts_as_sender' => true,
    //         'advertisements' => ['invoice'],
    //         'city' => $this->company->settings->city,
    //         'country' => 'DE',
    //         'county' => $this->company->settings->state,
    //         'line1' => $this->company->settings->address1,
    //         'line2' => $this->company->settings->address2,
    //         'party_name' => $this->company->present()->name(),
    //         'tax_registered' => true,
    //         'tenant_id' => $this->company->company_key,
    //         'zip' => $this->company->settings->postal_code,
    //         'peppol_identifiers' => [
    //             'scheme' => 'DE:VAT',
    //             'id' => 'DE:VAT'
    //         ],
    //     ];

    //     $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
    //     $r = $sc->createLegalEntity($data, $this->company);

    //     $this->assertIsArray($r);

    // }

    // public function testAddPeppolIdentifier()
    // {
        
    //         $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
    //         $r = $sc->addIdentifier(290868, "DE923356489", "DE:VAT");

    //         nlog($r);
        
    // }

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

    }    

    public function testSendDocument()
    {

        $x = '
        <?xml version="1.0" encoding="utf-8"?>
        <Invoice
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            xmlns:xsd="http://www.w3.org/2001/XMLSchema"
            xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">
  <cbc:ID>DE-77323</cbc:ID>
  <cbc:IssueDate>2024-07-18</cbc:IssueDate>
  <cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>
  <cac:AccountingSupplierParty>
    <cac:Party>
      <cac:PartyName>
        <cbc:Name>Untitled Company</cbc:Name>
      </cac:PartyName>
      <cac:PostalAddress>
        <cbc:StreetName>Dudweilerstr. 34b</cbc:StreetName>
        <cbc:CityName>Ost Alessa</cbc:CityName>
        <cbc:PostalZone>98060</cbc:PostalZone>
        <cbc:CountrySubentity>Bayern</cbc:CountrySubentity>
        <cac:Country>
          <cbc:IdentificationCode>DE</cbc:IdentificationCode>
        </cac:Country>
      </cac:PostalAddress>
      <cac:PhysicalLocation>
        <cbc:StreetName>Dudweilerstr. 34b</cbc:StreetName>
        <cbc:CityName>Ost Alessa</cbc:CityName>
        <cbc:PostalZone>98060</cbc:PostalZone>
        <cbc:CountrySubentity>Bayern</cbc:CountrySubentity>
        <cac:Country>
          <cbc:IdentificationCode>DE</cbc:IdentificationCode>
        </cac:Country>
      </cac:PhysicalLocation>
      <cac:Contact>
        <cbc:ElectronicMail>owner@gmail.com</cbc:ElectronicMail>
      </cac:Contact>
    </cac:Party>
  </cac:AccountingSupplierParty>
  <cac:AccountingCustomerParty>
    <cac:Party>
      <cac:PartyName>
        <cbc:Name>German Client Name</cbc:Name>
      </cac:PartyName>
      <cac:PostalAddress>
        <cbc:StreetName>Kinderhausen 96b</cbc:StreetName>
        <cbc:CityName>S&#xFC;d Jessestadt</cbc:CityName>
        <cbc:PostalZone>33323</cbc:PostalZone>
        <cbc:CountrySubentity>Bayern</cbc:CountrySubentity>
        <cac:Country>
          <cbc:IdentificationCode>DE</cbc:IdentificationCode>
        </cac:Country>
      </cac:PostalAddress>
      <cac:PhysicalLocation>
        <cbc:StreetName>Kinderhausen 96b</cbc:StreetName>
        <cbc:CityName>S&#xFC;d Jessestadt</cbc:CityName>
        <cbc:PostalZone>33323</cbc:PostalZone>
        <cbc:CountrySubentity>Bayern</cbc:CountrySubentity>
        <cac:Country>
          <cbc:IdentificationCode>DE</cbc:IdentificationCode>
        </cac:Country>
      </cac:PhysicalLocation>
      <cac:Contact>
        <cbc:ElectronicMail>No Email Set</cbc:ElectronicMail>
      </cac:Contact>
    </cac:Party>
  </cac:AccountingCustomerParty>
  <cac:PaymentMeans>
    <PayeeFinancialAccount>
      <ID>DE89370400440532013000</ID>
      <Name>PFA-NAME</Name>
      <AliasName>PFA-Alias</AliasName>
      <AccountTypeCode>CHECKING</AccountTypeCode>
      <AccountFormatCode>IBAN</AccountFormatCode>
      <CurrencyCode>EUR</CurrencyCode>
      <FinancialInstitutionBranch>
        <ID>DEUTDEMMXXX</ID>
        <Name>Deutsche Bank</Name>
      </FinancialInstitutionBranch>
    </PayeeFinancialAccount>
  </cac:PaymentMeans>
  <cac:TaxTotal/>
  <cac:LegalMonetaryTotal>
    <cbc:LineExtensionAmount currencyID="EUR">100</cbc:LineExtensionAmount>
    <cbc:TaxExclusiveAmount currencyID="EUR">100</cbc:TaxExclusiveAmount>
    <cbc:TaxInclusiveAmount currencyID="EUR">119.00</cbc:TaxInclusiveAmount>
    <cbc:PayableAmount currencyID="EUR">119.00</cbc:PayableAmount>
  </cac:LegalMonetaryTotal>
  <cac:InvoiceLine>
    <cbc:ID>1</cbc:ID>
    <cbc:InvoicedQuantity>10</cbc:InvoicedQuantity>
    <cbc:LineExtensionAmount currencyID="EUR">100</cbc:LineExtensionAmount>
    <cac:TaxTotal>
      <cbc:TaxAmount currencyID="EUR">19</cbc:TaxAmount>
      <cac:TaxSubtotal>
        <cbc:TaxableAmount currencyID="EUR">100</cbc:TaxableAmount>
        <cbc:TaxAmount currencyID="EUR">19</cbc:TaxAmount>
        <cac:TaxCategory>
          <cbc:ID>C62</cbc:ID>
          <cbc:Percent>19</cbc:Percent>
          <cac:TaxScheme>
            <cbc:ID>mwst</cbc:ID>
          </cac:TaxScheme>
        </cac:TaxCategory>
      </cac:TaxSubtotal>
    </cac:TaxTotal>
    <cac:Item>
      <cbc:Description>Product Description</cbc:Description>
      <cbc:Name>Product Key</cbc:Name>
    </cac:Item>
    <cac:Price>
      <cbc:PriceAmount currencyID="EUR">10</cbc:PriceAmount>
    </cac:Price>
  </cac:InvoiceLine>
  ';

//inclusive
$x = '<?xml version="1.0" encoding="utf-8"?>
<Invoice
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">
<cbc:ID>DE-93090</cbc:ID>
  <cbc:IssueDate>2024-07-18</cbc:IssueDate>
  <cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>
  <cac:AccountingSupplierParty>
    <cac:Party>
      <cac:PartyName>
        <cbc:Name>Untitled Company</cbc:Name>
      </cac:PartyName>
      <cac:PostalAddress>
        <cbc:StreetName>Dudweilerstr. 34b</cbc:StreetName>
        <cbc:CityName>Ost Alessa</cbc:CityName>
        <cbc:PostalZone>98060</cbc:PostalZone>
        <cbc:CountrySubentity>Bayern</cbc:CountrySubentity>
        <cac:Country>
          <cbc:IdentificationCode>DE</cbc:IdentificationCode>
        </cac:Country>
      </cac:PostalAddress>
      <cac:PhysicalLocation>
        <cbc:StreetName>Dudweilerstr. 34b</cbc:StreetName>
        <cbc:CityName>Ost Alessa</cbc:CityName>
        <cbc:PostalZone>98060</cbc:PostalZone>
        <cbc:CountrySubentity>Bayern</cbc:CountrySubentity>
        <cac:Country>
          <cbc:IdentificationCode>DE</cbc:IdentificationCode>
        </cac:Country>
      </cac:PhysicalLocation>
      <cac:Contact>
        <cbc:ElectronicMail>owner@gmail.com</cbc:ElectronicMail>
      </cac:Contact>
    </cac:Party>
  </cac:AccountingSupplierParty>
  <cac:AccountingCustomerParty>
    <cac:Party>
      <cac:PartyName>
        <cbc:Name>German Client Name</cbc:Name>
      </cac:PartyName>
      <cac:PostalAddress>
        <cbc:StreetName>Kinderhausen 96b</cbc:StreetName>
        <cbc:CityName>S&#xFC;d Jessestadt</cbc:CityName>
        <cbc:PostalZone>33323</cbc:PostalZone>
        <cbc:CountrySubentity>Bayern</cbc:CountrySubentity>
        <cac:Country>
          <cbc:IdentificationCode>DE</cbc:IdentificationCode>
        </cac:Country>
      </cac:PostalAddress>
      <cac:PhysicalLocation>
        <cbc:StreetName>Kinderhausen 96b</cbc:StreetName>
        <cbc:CityName>S&#xFC;d Jessestadt</cbc:CityName>
        <cbc:PostalZone>33323</cbc:PostalZone>
        <cbc:CountrySubentity>Bayern</cbc:CountrySubentity>
        <cac:Country>
          <cbc:IdentificationCode>DE</cbc:IdentificationCode>
        </cac:Country>
      </cac:PhysicalLocation>
      <cac:Contact>
        <cbc:ElectronicMail>No Email Set</cbc:ElectronicMail>
      </cac:Contact>
    </cac:Party>
  </cac:AccountingCustomerParty>
  <cac:PaymentMeans>
    <PayeeFinancialAccount>
      <ID>DE89370400440532013000</ID>
      <Name>PFA-NAME</Name>
      <AliasName>PFA-Alias</AliasName>
      <AccountTypeCode>CHECKING</AccountTypeCode>
      <AccountFormatCode>IBAN</AccountFormatCode>
      <CurrencyCode>EUR</CurrencyCode>
      <FinancialInstitutionBranch>
        <ID>DEUTDEMMXXX</ID>
        <Name>Deutsche Bank</Name>
      </FinancialInstitutionBranch>
    </PayeeFinancialAccount>
  </cac:PaymentMeans>
  <cac:TaxTotal>
    <cbc:TaxAmount currencyID="EUR">15.97</cbc:TaxAmount>
    <cac:TaxSubtotal>
      <cbc:TaxableAmount currencyID="EUR">84.03</cbc:TaxableAmount>
      <cbc:TaxAmount currencyID="EUR">15.97</cbc:TaxAmount>
      <cac:TaxCategory>
        <cbc:ID>C62</cbc:ID>
        <cbc:Percent>0</cbc:Percent>
        <cac:TaxScheme>
          <cbc:ID></cbc:ID>
        </cac:TaxScheme>
      </cac:TaxCategory>
    </cac:TaxSubtotal>
  </cac:TaxTotal>
  <cac:LegalMonetaryTotal>
    <cbc:LineExtensionAmount currencyID="EUR">84.03</cbc:LineExtensionAmount>
    <cbc:TaxExclusiveAmount currencyID="EUR">84.03</cbc:TaxExclusiveAmount>
    <cbc:TaxInclusiveAmount currencyID="EUR">100.00</cbc:TaxInclusiveAmount>
    <cbc:PayableAmount currencyID="EUR">100.00</cbc:PayableAmount>
  </cac:LegalMonetaryTotal>
  <cac:InvoiceLine>
    <cbc:ID>1</cbc:ID>
    <cbc:InvoicedQuantity>10</cbc:InvoicedQuantity>
    <cbc:LineExtensionAmount currencyID="EUR">84.03</cbc:LineExtensionAmount>
    <cac:TaxTotal>
      <cbc:TaxAmount currencyID="EUR">15.97</cbc:TaxAmount>
      <cac:TaxSubtotal>
        <cbc:TaxableAmount currencyID="EUR">84.03</cbc:TaxableAmount>
        <cbc:TaxAmount currencyID="EUR">15.97</cbc:TaxAmount>
        <cac:TaxCategory>
          <cbc:ID>C62</cbc:ID>
          <cbc:Percent>19</cbc:Percent>
          <cac:TaxScheme>
            <cbc:ID>mwst</cbc:ID>
          </cac:TaxScheme>
        </cac:TaxCategory>
      </cac:TaxSubtotal>
    </cac:TaxTotal>
    <cac:Item>
      <cbc:Description>Product Description</cbc:Description>
      <cbc:Name>Product Key</cbc:Name>
    </cac:Item>
    <cac:Price>
      <cbc:PriceAmount currencyID="EUR">8.403</cbc:PriceAmount>
    </cac:Price>
  </cac:InvoiceLine>
';


        $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
        $sc->sendDocument($x);

    }

}
