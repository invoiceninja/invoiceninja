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

namespace Tests\Integration\Einvoice;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Invoice;
use Tests\MockAccountData;
use App\Models\ClientContact;
use App\DataMapper\ClientSettings;
use App\DataMapper\InvoiceItem;
use CleverIt\UBL\Invoice\Party;
use Contact;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Invoiceninja\Einvoice\Models\FACT1\AddressType\PostalAddress;
use Invoiceninja\Einvoice\Models\FACT1\ContactType\Contact;
use Invoiceninja\Einvoice\Models\FACT1\CountryType\Country;
use Invoiceninja\Einvoice\Models\FACT1\CustomerPartyType\AccountingCustomerParty;
use Invoiceninja\Einvoice\Models\FACT1\PartyIdentificationType\PartyIdentification;
use Invoiceninja\Einvoice\Models\FACT1\PartyLegalEntityType\PartyLegalEntity;
use Invoiceninja\Einvoice\Models\FACT1\PartyTaxSchemeType\PartyTaxScheme;
use Invoiceninja\Einvoice\Models\FACT1\PartyType\Party;
use Invoiceninja\Einvoice\Models\FACT1\SupplierPartyType\AccountingSupplierParty;
use Invoiceninja\Einvoice\Models\FACT1\TaxSchemeType\TaxScheme;

/**
 * @test
 */
class Fact1Test extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testRoBuild()
    {
        $settings = ClientSettings::defaults();

//VAT
//19%
        $client = Client::factory()
        ->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'id_number' => '646546549',
            'address1' => '40D, Șoseaua București-Ploiești',
            'city' => 'SECTOR3',
            'state' => 'RO-B',
            'country_id' => 642,
            'vat_number' => 646546549,
            'name' => 'Client Company Name',
        ]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'first_name' => 'Bob',
            'last_name' => 'Jane',
            'email' => 'bob@gmail.com',
        ]);

        $items = [];

        $item = new InvoiceItem;
        $item->cost = 10;
        $item->quantity = 10;
        $item->tax_name1 = 'VAT';
        $item->tax_rate1 = '19';

        $_invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'number' => 'INV-'.rand(1000,1000000),
            'line_items' => [$item],
            'due_date' => now()->addDays(20)->format('Y-m-d'),
            'status_id' => 1,
            'discount' => 0,
        ]);
        
        $_invoice->service()->markSent()->save();
        $calc = $_invoice->calc();
        
        $invoice = new \InvoiceNinja\Einvoice\Models\FACT1\Invoice;
        $invoice->UBLVersionID = '2.1';
        $invoice->CustomizationID = 'urn:cen.eu:en16931:2017#compliant#urn:efactura.mfinante.ro:CIUS-RO:1.0.1';
        $invoice->ID = $_invoice->number;
        $invoice->InvoiceTypeCode = 380;
        $invoice->IssueDate = $_invoice->date;
        $invoice->DueDate = $_invoice->due_date;
        $invoice->DocumentCurrencyCode = 'RON';
        $invoice->TaxCurrencyCode = 'RON';

        $asp = new AccountingSupplierParty();
        $party = new Party();
        
        $party_identification = new PartyIdentification();
        $party_identification->ID = 'company_id_number';
        $party->PartyIdentification = $party_identification;
        
        $sp_address = new PostalAddress();
        $sp_address->StreetName = $this->company->settings->address1;
        $sp_address->CityName = 'SECTOR2';
        $sp_address->CountrySubentity = 'RO-B';

        $country = new Country();
        $country->IdentificationCode='RO';
        $sp_address->Country = $country;

        $party->PostalAddress = $sp_address;

        $pts = new PartyTaxScheme();
        $tax_scheme = new TaxScheme();
        $tax_scheme->ID = 'VAT';

        $pts->CompanyID = 'RO234234234';
        $pts->TaxScheme = $tax_scheme;

        $party->PartyTaxScheme = $pts;

        $ple = new PartyLegalEntity();
        $ple->RegistrationName = $this->company->settings->name;
        $ple->CompanyID = 'J40/2222/2009';

        $party->PartyLegalEntity = $ple;

        $p_contact = new Contact();
        $p_contact->Name = $this->company->owner()->present()->name();
        $p_contact->Telephone = $this->company->settings->phone;
        $p_contact->ElectronicMail = $this->company->owner()->present()->email();

        $party->Contact = $p_contact;
        $asp->Party = $party;

        $invoice->AccountingSupplierParty = $asp;

        $acp = new AccountingCustomerParty();

        $party = new Party();

        $party_identification = new PartyIdentification();
        $party_identification->ID = 'client_id_number';
        $party->PartyIdentification = $party_identification;

        $sp_address = new PostalAddress();
        $sp_address->StreetName = $client->address1;
        $sp_address->CityName = 'SECTOR2';
        $sp_address->CountrySubentity = 'RO-B';

        $country = new Country();
        $country->IdentificationCode = 'RO';
        $sp_address->Country = $country;

        $party->PostalAddress = $sp_address;

        $ple = new PartyLegalEntity();
        $ple->RegistrationName = $client->name;
        $ple->CompanyID = '646546549';

        $party->PartyLegalEntity = $ple;

        $p_contact = new Contact();
        $p_contact->Name = $client->contacts->first()->present()->name();
        $p_contact->Telephone = $client->contacts->first()->present()->phone();
        $p_contact->ElectronicMail = $client->contacts->first()->present()->email();

        $party->Contact = $p_contact;

        $acp->Party = $party;
        $invoice->AccountingCustomerParty = $acp;




        
        //set default standard props
    }
}
