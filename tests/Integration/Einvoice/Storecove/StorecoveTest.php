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
use App\Models\ClientContact;
use App\Services\EDocument\Standards\Peppol;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans;

class StorecoveTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private int $routing_id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        if (config('ninja.testvars.travis') !== false || !config('ninja.storecove_api_key')) {
            $this->markTestSkipped("do not run in CI");
        }
    }

    // public function testCreateLegalEntity()
    // {

    // $data = [
    //     'acts_as_receiver' => true,
    //     'acts_as_sender' => true,
    //     'advertisements' => ['invoice'],
    //     'city' => $this->company->settings->city,
    //     'country' => 'DE',
    //     'county' => $this->company->settings->state,
    //     'line1' => $this->company->settings->address1,
    //     'line2' => $this->company->settings->address2,
    //     'party_name' => $this->company->present()->name(),
    //     'tax_registered' => true,
    //     'tenant_id' => $this->company->company_key,
    //     'zip' => $this->company->settings->postal_code,
    //     'peppol_identifiers' => [
    //         'scheme' => 'DE:VAT',
    //         'id' => 'DE:VAT'
    //     ],
    // ];

    // $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
    // $r = $sc->createLegalEntity($data, $this->company);

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
            $sc->sendDocument($x, 290868);

        }
    */
    public function XXestCreateCHClient()
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


    private function createITData($business = true)
    {

        $this->routing_id = 294636;

        $settings = CompanySettings::defaults();
        $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
        $settings->website = 'www.invoiceninja.it';
        $settings->address1 = 'Via del Corso, 28';
        $settings->address2 = 'Palazzo delle Telecomunicazioni';
        $settings->city = 'Roma';
        $settings->state = 'Lazio';
        $settings->postal_code = '00187';
        $settings->phone = '06 1234567';
        $settings->email = $this->faker->unique()->safeEmail();
        $settings->country_id = '380'; // Italy's ISO country code
        $settings->vat_number = 'IT92443356490'; // Italian VAT number
        $settings->id_number = 'RM 123456'; // Typical Italian company registration format
        $settings->use_credits_payment = 'always';
        $settings->timezone_id = '1'; // CET (Central European Time)
        $settings->entity_send_time = 0;
        $settings->e_invoice_type = 'PEPPOL';
        $settings->currency_id = '3'; // Euro (EUR)
        $settings->classification = 'business';


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
          'name' => 'Impresa Esempio S.p.A.',
          'website' => 'https://www.impresa-esempio.it',
          'private_notes' => 'Queste sono note private per il cliente di prova.',
          'balance' => 0,
          'paid_to_date' => 0,
          'vat_number' => 'IT92443356489', // Italian VAT number with IT prefix
          'id_number' => 'B12345678', // Typical format for Italian company registration numbers
          'custom_value1' => '2024-07-22 10:00:00',
          'custom_value2' => 'blu', // Italian for blue
          'custom_value3' => 'parolaesempio', // Italian for sample word
          'custom_value4' => 'test@esempio.it',
          'address1' => 'Via Esempio 123',
          'address2' => '2º Piano, Ufficio 45',
          'city' => 'Roma',
          'state' => 'Lazio',
          'postal_code' => '00187',
          'country_id' => '380', // Italy
          'shipping_address1' => 'Via Esempio 123',
          'shipping_address2' => '2º Piano, Ufficio 45',
          'shipping_city' => 'Roma',
          'shipping_state' => 'Lazio',
          'shipping_postal_code' => '00187',
          'shipping_country_id' => '380', // Italy
          'settings' => ClientSettings::defaults(),
          'client_hash' => \Illuminate\Support\Str::random(32),
          'routing_id' => 'SCSCSCS',
          'classification' => 'business',
        ]);

        ClientContact::factory()->create([
          'company_id' => $company->id,
          'user_id' => $this->user->id,
          'client_id' => $c->id,
          'first_name' => 'Contact First',
          'last_name' => 'Contact Last',
          'email' => 'david+c1@invoiceninja.com',
        ]);

        $c2 =
          Client::create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'name' => 'Impresa Esempio S.p.A.',
            'website' => 'https://www.impresa-esempio.it',
            'private_notes' => 'Queste sono note private per il cliente di prova.',
            'balance' => 0,
            'paid_to_date' => 0,
            'vat_number' => 'RSSMRA85M01H501Z', // Italian VAT number with IT prefix
            'id_number' => 'B12345678', // Typical format for Italian company registration numbers
            'custom_value1' => '2024-07-22 10:00:00',
            'custom_value2' => 'blu', // Italian for blue
            'custom_value3' => 'parolaesempio', // Italian for sample word
            'custom_value4' => 'test@esempio.it',
            'address1' => 'Via Esempio 123',
            'address2' => '2º Piano, Ufficio 45',
            'city' => 'Roma',
            'state' => 'Lazio',
            'postal_code' => '00187',
            'country_id' => '380', // Italy
            'shipping_address1' => 'Via Esempio 123',
            'shipping_address2' => '2º Piano, Ufficio 45',
            'shipping_city' => 'Roma',
            'shipping_state' => 'Lazio',
            'shipping_postal_code' => '00187',
            'shipping_country_id' => '380', // Italy
            'settings' => ClientSettings::defaults(),
            'client_hash' => \Illuminate\Support\Str::random(32),
            'routing_id' => 'SCSCSCS',
            'classification' => 'individual',
          ]);


        ClientContact::factory()->create([
          'company_id' => $company->id,
          'user_id' => $this->user->id,
          'client_id' => $c2->id,
          'first_name' => 'Contact First',
          'last_name' => 'Contact Last',
          'email' => 'david+c2@invoiceninja.com',
        ]);


        $item = new InvoiceItem();
        $item->product_key = "Product Key";
        $item->notes = "Product Description";
        $item->cost = 10;
        $item->quantity = 10;
        $item->tax_rate1 = 22;
        $item->tax_name1 = 'IVA';

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'client_id' => $business ? $c->id : $c2->id,
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
            'number' => 'IT-'.rand(1000, 100000),
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(14)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();

        return $invoice;

    }

    private function createESData()
    {
        $this->routing_id = 293098;

        $settings = CompanySettings::defaults();
        $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
        $settings->website = 'www.invoiceninja.de';
        $settings->address1 = 'Calle Gran Vía, 28';
        $settings->address2 = 'Edificio Telefónica';
        $settings->city = 'Madrid';
        $settings->state = 'Madrid';
        $settings->postal_code = '28013';
        $settings->phone = '030 1234567';
        $settings->email = $this->faker->unique()->safeEmail();
        $settings->country_id = '724'; // Germany's ISO country code
        $settings->vat_number = 'ESB16645678';
        $settings->id_number = 'HRB 12345';
        $settings->use_credits_payment = 'always';
        $settings->timezone_id = '1'; // CET (Central European Time)
        $settings->entity_send_time = 0;
        $settings->e_invoice_type = 'PEPPOL';
        $settings->currency_id = '3';
        $settings->classification = 'business';

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
          'name' => 'Empresa Ejemplo S.A.',
          'website' => 'https://www.empresa-ejemplo.es',
          'private_notes' => 'Estas son notas privadas para el cliente de prueba.',
          'balance' => 0,
          'paid_to_date' => 0,
          'vat_number' => 'ESB12345678', // Spanish VAT number with ES prefix
          'id_number' => 'B12345678', // Typical format for Spanish company registration numbers
          'custom_value1' => '2024-07-22 10:00:00',
          'custom_value2' => 'azul', // Spanish for blue
          'custom_value3' => 'palabraejemplo', // Spanish for sample word
          'custom_value4' => 'test@ejemplo.com',
          'address1' => 'Calle Ejemplo 123',
          'address2' => '2ª Planta, Oficina 45',
          'city' => 'Madrid',
          'state' => 'Madrid',
          'postal_code' => '28013',
          'country_id' => '724', // Spain
          'shipping_address1' => 'Calle Ejemplo 123',
          'shipping_address2' => '2ª Planta, Oficina 45',
          'shipping_city' => 'Madrid',
          'shipping_state' => 'Madrid',
          'shipping_postal_code' => '28013',
          'shipping_country_id' => '724', // Spain
          'settings' => ClientSettings::Defaults(),
          'client_hash' => \Illuminate\Support\Str::random(32),
          'routing_id' => 'SCSCSC',
        ]);

        $item = new InvoiceItem();
        $item->product_key = "Product Key";
        $item->notes = "Product Description";
        $item->cost = 10;
        $item->quantity = 10;
        $item->tax_rate1 = 21;
        $item->tax_name1 = 'IVA';

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
            'number' => 'ES-'.rand(1000, 100000),
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(14)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();

        return $invoice;

    }

    private function createFRData()
    {
        $this->routing_id = 293338;

        $settings = CompanySettings::defaults();
        $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
        $settings->website = 'www.invoiceninja.de';

        $settings->address1 = '10 Rue de la Paix';
        $settings->address2 = 'Bâtiment A, Bureau 5';
        $settings->city = 'Paris';
        $settings->state = 'Île-de-France';
        $settings->postal_code = '75002';
        $settings->phone = '01 23456789';
        $settings->email = $this->faker->unique()->safeEmail();
        $settings->country_id = '250'; // France's ISO country code
        $settings->vat_number = 'FR82345678911';
        $settings->id_number = '12345678900010';
        $settings->classification = 'business';
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
          'name' => 'Exemple Société S.A.',
          'website' => 'https://www.exemple-societe.fr',
          'private_notes' => 'Ceci est une note privée pour le client test.',
          'balance' => 0,
          'paid_to_date' => 0,
          'vat_number' => 'FR12345678901',
          'id_number' => '12345678900010', // Typical format for French company registration numbers
          'custom_value1' => '2024-07-22 10:00:00',
          'custom_value2' => 'bleu',
          'custom_value3' => 'motexemple',
          'custom_value4' => 'test@example.com',
          'address1' => '123 Rue de l\'Exemple',
          'address2' => '2ème étage, Bureau 45',
          'city' => 'Paris',
          'state' => 'Île-de-France',
          'postal_code' => '75001',
          'country_id' => '250', // France
          'shipping_address1' => '123 Rue de l\'Exemple',
          'shipping_address2' => '2ème étage, Bureau 45',
          'shipping_city' => 'Paris',
          'shipping_state' => 'Île-de-France',
          'shipping_postal_code' => '75001',
          'shipping_country_id' => '250', // France
          'classification' => 'business',
          'settings' => ClientSettings::Defaults(),
          'client_hash' => \Illuminate\Support\Str::random(32),
          'routing_id' => '',
        ]);


        $item = new InvoiceItem();
        $item->product_key = "Product Key";
        $item->notes = "Product Description";
        $item->cost = 10;
        $item->quantity = 10;
        $item->tax_rate1 = 20;
        $item->tax_name1 = 'VAT';

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
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(14)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();


        return $invoice;

    }

    private function createATData(bool $is_gov = false)
    {

        $this->routing_id = 293801;

        $settings = CompanySettings::defaults();
        $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
        $settings->website = 'www.invoiceninja.at';
        $settings->address1 = 'Musterstraße 1';
        $settings->address2 = 'Stockwerk 2, Büro 3';
        $settings->city = 'Vienna';
        $settings->state = 'Vienna';
        $settings->postal_code = '1010';
        $settings->phone = '+43 1 23456789';
        $settings->email = $this->faker->unique()->safeEmail();
        $settings->country_id = '40'; // Austria's ISO country code
        $settings->vat_number = 'ATU92335648';
        $settings->id_number = 'FN 123456x';
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
          'website' => 'https://www.beispiel-firma.at',
          'private_notes' => 'Dies sind private Notizen zum Testkunden.',
          'balance' => 0,
          'paid_to_date' => 0,
          'vat_number' => 'ATU87654321',
          'id_number' => $is_gov ? 'ATU12312321' : 'FN 123456x', // Example format for Austrian company registration numbers
          'custom_value1' => '2024-07-22 10:00:00',
          'custom_value2' => 'blau',
          'custom_value3' => 'musterwort',
          'custom_value4' => 'test@example.com',
          'address1' => 'Musterstraße 123',
          'address2' => '2. Etage, Büro 45',
          'city' => 'Vienna',
          'state' => 'Vienna',
          'postal_code' => '1010',
          'country_id' => '40', // Austria
          'shipping_address1' => 'Musterstraße 123',
          'shipping_address2' => '2. Etage, Büro 45',
          'shipping_city' => 'Vienna',
          'shipping_state' => 'Vienna',
          'shipping_postal_code' => '1010',
          'shipping_country_id' => '40', // Austria
          'settings' => ClientSettings::Defaults(),
          'client_hash' => \Illuminate\Support\Str::random(32),
          'routing_id' => '',
          'classification' => $is_gov ? 'government' : 'business',
        ]);


        $item = new InvoiceItem();
        $item->product_key = "Product Key";
        $item->notes = "Product Description";
        $item->cost = 10;
        $item->quantity = 10;
        $item->tax_rate1 = 20;
        $item->tax_name1 = 'VAT';

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
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(14)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();

        return $invoice;

    }

    private function createROData()
    {
        $this->routing_id = 294639;

        $settings = CompanySettings::defaults();
        $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
        $settings->website = 'www.invoiceninja.ro';
        $settings->address1 = 'Strada Exemplu, 28';
        $settings->address2 = 'Clădirea Exemplu';
        $settings->city = 'Bucharest';
        $settings->state = 'Bucharest';
        $settings->postal_code = '010101';
        $settings->phone = '021 1234567';
        $settings->email = $this->faker->unique()->safeEmail();
        $settings->country_id = '642'; // Romania's ISO country code
        $settings->vat_number = 'RO92443356490'; // Romanian VAT number format
        $settings->id_number = 'B12345678'; // Typical Romanian company registration format
        $settings->use_credits_payment = 'always';
        $settings->timezone_id = '1'; // CET (Central European Time)
        $settings->entity_send_time = 0;
        $settings->e_invoice_type = 'PEPPOL';
        $settings->currency_id = '3'; // Euro (EUR)
        $settings->classification = 'business';


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
          'name' => 'Impresa Esempio S.R.L.',
          'website' => 'https://www.impresa-esempio.ro',
          'private_notes' => 'Acestea sunt note private pentru clientul de test.',
          'balance' => 0,
          'paid_to_date' => 0,
          'vat_number' => 'RO9244336489', // Romanian VAT number with RO prefix
          'id_number' => 'J40/12345/2024', // Typical format for Romanian company registration numbers
          'custom_value1' => '2024-07-22 10:00:00',
          'custom_value2' => 'albastru', // Romanian for blue
          'custom_value3' => 'cuvantexemplu', // Romanian for sample word
          'custom_value4' => 'test@exemplu.ro',
          'address1' => 'Strada Exemplu 123',
          'address2' => 'Etaj 2, Birou 45',
          'city' => 'Bucharest',
          'state' => 'Bucharest',
          'postal_code' => '010101',
          'country_id' => '642', // Romania
          'shipping_address1' => 'Strada Exemplu 123',
          'shipping_address2' => 'Etaj 2, Birou 45',
          'shipping_city' => 'Bucharest',
          'shipping_state' => 'Bucharest',
          'shipping_postal_code' => '010101',
          'shipping_country_id' => '642', // Romania
          'settings' => ClientSettings::defaults(),
          'client_hash' => \Illuminate\Support\Str::random(32),
          'routing_id' => 'SCSCSCS',
          'classification' => 'business',
        ]);

        ClientContact::factory()->create([
          'company_id' => $company->id,
          'user_id' => $this->user->id,
          'client_id' => $c->id,
          'first_name' => 'Contact First',
          'last_name' => 'Contact Last',
          'email' => 'david+c1@invoiceninja.com',
        ]);


        $item = new InvoiceItem();
        $item->product_key = "Product Key";
        $item->notes = "Product Description";
        $item->cost = 10;
        $item->quantity = 10;
        $item->tax_rate1 = 19;
        $item->tax_name1 = 'TVA';

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
            'number' => 'IT-'.rand(1000, 100000),
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(14)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();

        return $invoice;

    }

    public function testRoRules()
    {
        $invoice = $this->createROData();

        $e_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $stub = json_decode('{"Invoice":{"Note":"Nooo","PaymentMeans":[{"ID":{"value":"afdasfasdfasdfas"},"PayeeFinancialAccount":{"Name":"PFA-NAME","ID":{"value":"DE89370400440532013000"},"AliasName":"PFA-Alias","AccountTypeCode":{"value":"CHECKING"},"AccountFormatCode":{"value":"IBAN"},"CurrencyCode":{"value":"EUR"},"FinancialInstitutionBranch":{"ID":{"value":"DEUTDEMMXXX"},"Name":"Deutsche Bank"}}}]}}');
        foreach($stub as $key => $value) {
            $e_invoice->{$key} = $value;
        }

        $invoice->e_invoice = $e_invoice;
        $invoice->save();

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertInstanceof(\InvoiceNinja\EInvoice\Models\Peppol\Invoice::class, $e_invoice);

        $p = new Peppol($invoice);

        $p->run();
        $xml  = $p->toXml();
        nlog($xml);

        $identifiers = $p->getStorecoveMeta();

        $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
        $sc->sendDocument($xml, $this->routing_id, $identifiers);

    }




    public function PestAtGovernmentRules()
    {
        $this->routing_id = 293801;

        $invoice = $this->createATData(true);

        $e_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $stub = json_decode('{"Invoice":{"Note":"Nooo","PaymentMeans":[{"ID":{"value":"afdasfasdfasdfas"},"PayeeFinancialAccount":{"Name":"PFA-NAME","ID":{"value":"DE89370400440532013000"},"AliasName":"PFA-Alias","AccountTypeCode":{"value":"CHECKING"},"AccountFormatCode":{"value":"IBAN"},"CurrencyCode":{"value":"EUR"},"FinancialInstitutionBranch":{"ID":{"value":"DEUTDEMMXXX"},"Name":"Deutsche Bank"}}}]}}');
        foreach($stub as $key => $value) {
            $e_invoice->{$key} = $value;
        }

        $invoice->e_invoice = $e_invoice;
        $invoice->save();

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertInstanceof(\InvoiceNinja\EInvoice\Models\Peppol\Invoice::class, $e_invoice);

        $p = new Peppol($invoice);

        $p->run();
        $xml  = $p->toXml();
        nlog($xml);

        $identifiers = $p->getStorecoveMeta();

        $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
        $sc->sendDocument($xml, $this->routing_id, $identifiers);

    }

    public function PtestItRules()
    {
        $invoice = $this->createITData();

        $e_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $stub = json_decode('{"Invoice":{"Note":"Nooo","PaymentMeans":[{"ID":{"value":"afdasfasdfasdfas"},"PayeeFinancialAccount":{"Name":"PFA-NAME","ID":{"value":"DE89370400440532013000"},"AliasName":"PFA-Alias","AccountTypeCode":{"value":"CHECKING"},"AccountFormatCode":{"value":"IBAN"},"CurrencyCode":{"value":"EUR"},"FinancialInstitutionBranch":{"ID":{"value":"DEUTDEMMXXX"},"Name":"Deutsche Bank"}}}]}}');
        foreach($stub as $key => $value) {
            $e_invoice->{$key} = $value;
        }

        $invoice->e_invoice = $e_invoice;
        $invoice->save();

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertInstanceof(\InvoiceNinja\EInvoice\Models\Peppol\Invoice::class, $e_invoice);

        $p = new Peppol($invoice);

        $p->run();
        $xml  = $p->toXml();
        nlog($xml);

        $identifiers = $p->getStorecoveMeta();

        $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
        $sc->sendDocument($xml, $this->routing_id, $identifiers);


        //test individual sending

        nlog("Individual");

        $invoice = $this->createITData(false);

        $e_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $stub = json_decode('{"Invoice":{"Note":"Nooo","PaymentMeans":[{"ID":{"value":"afdasfasdfasdfas"},"PayeeFinancialAccount":{"Name":"PFA-NAME","ID":{"value":"DE89370400440532013000"},"AliasName":"PFA-Alias","AccountTypeCode":{"value":"CHECKING"},"AccountFormatCode":{"value":"IBAN"},"CurrencyCode":{"value":"EUR"},"FinancialInstitutionBranch":{"ID":{"value":"DEUTDEMMXXX"},"Name":"Deutsche Bank"}}}]}}');
        foreach($stub as $key => $value) {
            $e_invoice->{$key} = $value;
        }

        $invoice->e_invoice = $e_invoice;
        $invoice->save();

        $p = new Peppol($invoice);

        $p->run();
        $xml  = $p->toXml();
        nlog($xml);

        $identifiers = $p->getStorecoveMeta();

        $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
        $sc->sendDocument($xml, $this->routing_id, $identifiers);


    }

    public function PestAtRules()
    {
        $this->routing_id = 293801;

        $invoice = $this->createATData();

        $e_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $stub = json_decode('{"Invoice":{"Note":"Nooo","PaymentMeans":[{"ID":{"value":"afdasfasdfasdfas"},"PayeeFinancialAccount":{"Name":"PFA-NAME","ID":{"value":"DE89370400440532013000"},"AliasName":"PFA-Alias","AccountTypeCode":{"value":"CHECKING"},"AccountFormatCode":{"value":"IBAN"},"CurrencyCode":{"value":"EUR"},"FinancialInstitutionBranch":{"ID":{"value":"DEUTDEMMXXX"},"Name":"Deutsche Bank"}}}]}}');
        foreach($stub as $key => $value) {
            $e_invoice->{$key} = $value;
        }

        $invoice->e_invoice = $e_invoice;
        $invoice->save();

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertInstanceof(\InvoiceNinja\EInvoice\Models\Peppol\Invoice::class, $e_invoice);

        $p = new Peppol($invoice);

        $p->run();
        $xml  = $p->toXml();
        nlog($xml);

        $identifiers = $p->getStorecoveMeta();

        $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
        $sc->sendDocument($xml, $this->routing_id, $identifiers);

    }

    public function PtestFrRules()
    {

        $invoice = $this->createFRData();

        $e_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $stub = json_decode('{"Invoice":{"Note":"Nooo","PaymentMeans":[{"ID":{"value":"afdasfasdfasdfas"},"PayeeFinancialAccount":{"Name":"PFA-NAME","ID":{"value":"DE89370400440532013000"},"AliasName":"PFA-Alias","AccountTypeCode":{"value":"CHECKING"},"AccountFormatCode":{"value":"IBAN"},"CurrencyCode":{"value":"EUR"},"FinancialInstitutionBranch":{"ID":{"value":"DEUTDEMMXXX"},"Name":"Deutsche Bank"}}}]}}');
        foreach($stub as $key => $value) {
            $e_invoice->{$key} = $value;
        }

        $invoice->e_invoice = $e_invoice;
        $invoice->save();

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertInstanceof(\InvoiceNinja\EInvoice\Models\Peppol\Invoice::class, $e_invoice);

        $p = new Peppol($invoice);

        $p->run();
        $xml  = $p->toXml();
        nlog($xml);

        $identifiers = $p->getStorecoveMeta();

        $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
        $sc->sendDocument($xml, $this->routing_id, $identifiers);

    }

    public function PtestEsRules()
    {

        $invoice = $this->createESData();

        $e_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $stub = json_decode('{"Invoice":{"Note":"Nooo","PaymentMeans":[{"ID":{"value":"afdasfasdfasdfas"},"PayeeFinancialAccount":{"Name":"PFA-NAME","ID":{"value":"DE89370400440532013000"},"AliasName":"PFA-Alias","AccountTypeCode":{"value":"CHECKING"},"AccountFormatCode":{"value":"IBAN"},"CurrencyCode":{"value":"EUR"},"FinancialInstitutionBranch":{"ID":{"value":"DEUTDEMMXXX"},"Name":"Deutsche Bank"}}}]}}');
        foreach($stub as $key => $value) {
            $e_invoice->{$key} = $value;
        }

        $invoice->e_invoice = $e_invoice;
        $invoice->save();

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertInstanceof(\InvoiceNinja\EInvoice\Models\Peppol\Invoice::class, $e_invoice);

        $p = new Peppol($invoice);

        $p->run();
        $xml  = $p->toXml();
        nlog($xml);

        $identifiers = [
          "routing" => [
            "eIdentifiers" => [
                [
                'scheme' => 'ES:VAT',
                'id' => 'ESB53625999'
                ],
            ]
          ]
        ];

        $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
        $sc->sendDocument($xml, $this->routing_id, $identifiers);

    }

    public function RtestDeRules()
    {
        $invoice = $this->createDEData();

        $e_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $stub = json_decode('{"Invoice":{"Note":"Nooo","PaymentMeans":[{"ID":{"value":"afdasfasdfasdfas"},"PayeeFinancialAccount":{"Name":"PFA-NAME","ID":{"value":"DE89370400440532013000"},"AliasName":"PFA-Alias","AccountTypeCode":{"value":"CHECKING"},"AccountFormatCode":{"value":"IBAN"},"CurrencyCode":{"value":"EUR"},"FinancialInstitutionBranch":{"ID":{"value":"DEUTDEMMXXX"},"Name":"Deutsche Bank"}}}]}}');
        foreach($stub as $key => $value) {
            $e_invoice->{$key} = $value;
        }

        $invoice->e_invoice = $e_invoice;
        $invoice->save();

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertInstanceof(\InvoiceNinja\EInvoice\Models\Peppol\Invoice::class, $e_invoice);

        $p = new Peppol($invoice);

        $p->run();
        $xml  = $p->toXml();
        nlog($xml);

        $identifiers = [
          "routing" => [
            "eIdentifiers" => [
              [
                'scheme' => 'DE:VAT',
                'id' => 'DE010101010'
              ]
            ]
          ]
        ];

        $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
        $sc->sendDocument($xml, $this->routing_id, $identifiers);


    }

}
