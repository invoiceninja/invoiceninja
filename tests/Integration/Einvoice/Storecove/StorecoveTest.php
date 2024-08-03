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
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use Tests\MockAccountData;
use App\DataMapper\InvoiceItem;
use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Services\EDocument\Standards\Peppol;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans;

class StorecoveTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private string $routing_id = '';

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
    //         $r = $sc->addIdentifier(291394, "DE923356489", "DE:VAT");

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
/*
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
*/
    public function testCreateCHClient()
    {
      
      Client::unguard();

      $c = 
      Client::create([
          'company_id' => $this->company->id,
          'user_id' => $this->user->id,
          'name' => 'Test Company AG',
          'website' => 'https://www.testcompany.ch',
          'private_notes' => 'These are some private notes about the test client.',
          'balance' => 0,
          'paid_to_date' => 0,
          'vat_number' => '654321987',
          'id_number' => 'CH9300762011623852957', // Sample Swiss IBAN
          'custom_value1' => '2024-07-22 10:00:00',
          'custom_value2' => 'blue',
          'custom_value3' => 'sampleword',
          'custom_value4' => 'test@example.com',
          'address1' => '123',
          'address2' => 'Test Street 45',
          'city' => 'Zurich',
          'state' => 'Zurich',
          'postal_code' => '8001',
          'country_id' => '756', // Switzerland
          'shipping_address1' => '123',
          'shipping_address2' => 'Test Street 45',
          'shipping_city' => 'Zurich',
          'shipping_state' => 'Zurich',
          'shipping_postal_code' => '8001',
          'shipping_country_id' => '756', // Switzerland
          'settings' => ClientSettings::Defaults(),
          'client_hash' => \Illuminate\Support\Str::random(32),
          'routing_id' => '',
      ]);


      $this->assertInstanceOf(\App\Models\Client::class, $c);

    }

    private function createDEData()
    {

      $this->routing_id = '290868';

      $settings = CompanySettings::defaults();
      $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
      $settings->website = 'www.invoiceninja.de';
      $settings->address1 = 'Musterstraße 1';
      $settings->address2 = 'Etage 2, Büro 3';
      $settings->city = 'Berlin';
      $settings->state = 'Berlin';
      $settings->postal_code = '10115';
      $settings->phone = '030 1234567';
      $settings->email = $this->faker->unique()->safeEmail();
      $settings->country_id = '276'; // Germany's ISO country code
      $settings->vat_number = 'DE123456789';
      $settings->id_number = 'HRB 12345';
      $settings->use_credits_payment = 'always';
      $settings->timezone_id = '1'; // CET (Central European Time)
      $settings->entity_send_time = 0;
      $settings->e_invoice_type = 'PEPPOL';
      $settings->currency_id = '3';

      $company = Company::factory()->create([
        'account_id' => $this->account->id,
        'settings' => $settings,
      ]);

      $this->user->companies()->attach($company->id, [
          'account_id' => $this->account->id,
          'is_owner' => true,
          'is_admin' => 1,
          'is_locked' => 0,
          'permissions' => '',
          'notifications' => CompanySettings::notificationAdminDefaults(),
          'settings' => null,
      ]);

    Client::unguard();

    $c =
    Client::create([
      'company_id' => $company->id,
      'user_id' => $this->user->id,
      'name' => 'Beispiel Firma GmbH',
      'website' => 'https://www.beispiel-firma.de',
      'private_notes' => 'Dies sind private Notizen zum Testkunden.',
      'balance' => 0,
      'paid_to_date' => 0,
      'vat_number' => 'DE654321987',
      'id_number' => 'HRB 12345', // Typical format for German company registration numbers
      'custom_value1' => '2024-07-22 10:00:00',
      'custom_value2' => 'blau',
      'custom_value3' => 'musterwort',
      'custom_value4' => 'test@example.com',
      'address1' => 'Musterstraße 123',
      'address2' => '2. Etage, Büro 45',
      'city' => 'München',
      'state' => 'Bayern',
      'postal_code' => '80331',
      'country_id' => '276', // Germany
      'shipping_address1' => 'Musterstraße 123',
      'shipping_address2' => '2. Etage, Büro 45',
      'shipping_city' => 'München',
      'shipping_state' => 'Bayern',
      'shipping_postal_code' => '80331',
      'shipping_country_id' => '276', // Germany
      'settings' => ClientSettings::Defaults(),
      'client_hash' => \Illuminate\Support\Str::random(32),
      'routing_id' => '',
    ]);


    $item = new InvoiceItem();
    $item->product_key = "Product Key";
    $item->notes = "Product Description";
    $item->cost = 10;
    $item->quantity = 10;
    $item->tax_rate1 = 19;
    $item->tax_name1 = 'mwst';

    $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'user_id' => $this->user->id,
        'client_id' => $c->id,
        'discount' => 0,
        'uses_inclusive_taxes' => false,
        'status_id' => 1,
        'tax_rate1' => 0,
        'tax_name1' => '',
        'tax_rate2' => 0,
        'tax_rate3' => 0,
        'tax_name2' => '',
        'tax_name3' => '',
        'line_items' => [$item],
        'number' => 'DE-'.rand(1000, 100000),
        'date' => now()->format('Y-m-d')
    ]);

    $invoice = $invoice->calc()->getInvoice();
    $invoice->service()->markSent()->save();


      return $invoice;

    }

    public function testDeRules()
    {
      $invoice = $this->createDEData();

      $e_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();
      
      $stub = json_decode('{"Invoice":{"Note":"Nooo","PaymentMeans":[{"ID":{"value":"afdasfasdfasdfas"},"PayeeFinancialAccount":{"Name":"PFA-NAME","ID":{"value":"DE89370400440532013000"},"AliasName":"PFA-Alias","AccountTypeCode":{"value":"CHECKING"},"AccountFormatCode":{"value":"IBAN"},"CurrencyCode":{"value":"EUR"},"FinancialInstitutionBranch":{"ID":{"value":"DEUTDEMMXXX"},"Name":"Deutsche Bank"}}}]}}');
      foreach($stub as $key => $value)
        $e_invoice->{$key} = $value;

      $invoice->e_invoice = $e_invoice;
      $invoice->save();

      $this->assertInstanceOf(Invoice::class, $invoice);
      $this->assertInstanceof(\InvoiceNinja\EInvoice\Models\Peppol\Invoice::class, $e_invoice);

      $p = new Peppol($invoice);

      $p->run();
      $xml  = $p->toXml();
      nlog($xml);
    $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
    $sc->sendDocument($xml);


    }

}
