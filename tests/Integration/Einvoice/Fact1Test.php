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

use DateTime;
use Tests\TestCase;
use App\Models\Client;
use App\Models\Invoice;
use Tests\MockAccountData;
use App\Models\ClientContact;
use App\DataMapper\InvoiceItem;
use App\DataMapper\ClientSettings;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use InvoiceNinja\EInvoice\Models\Peppol\ItemType\Item;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvoiceNinja\EInvoice\Models\Peppol\PartyType\Party;
use InvoiceNinja\EInvoice\Models\Peppol\PriceType\Price;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use InvoiceNinja\EInvoice\Models\Peppol\ContactType\Contact;
use InvoiceNinja\EInvoice\Models\Peppol\CountryType\Country;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxAmount;
use InvoiceNinja\EInvoice\Models\Peppol\TaxTotalType\TaxTotal;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\PriceAmount;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use InvoiceNinja\EInvoice\Models\Peppol\TaxSchemeType\TaxScheme;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use InvoiceNinja\EInvoice\Models\Peppol\AddressType\PostalAddress;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use InvoiceNinja\EInvoice\Models\Peppol\InvoiceLineType\InvoiceLine;
use InvoiceNinja\EInvoice\Models\Peppol\TaxScheme as PeppolTaxScheme;
use InvoiceNinja\EInvoice\Models\Peppol\TaxSubtotalType\TaxSubtotal;
use InvoiceNinja\EInvoice\Models\Peppol\QuantityType\InvoicedQuantity;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\LineExtensionAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\PayableAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxableAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxExclusiveAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxInclusiveAmount;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use InvoiceNinja\EInvoice\Models\Peppol\PartyTaxSchemeType\PartyTaxScheme;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use InvoiceNinja\EInvoice\Models\Peppol\MonetaryTotalType\LegalMonetaryTotal;
use InvoiceNinja\EInvoice\Models\Peppol\PartyLegalEntityType\PartyLegalEntity;
use InvoiceNinja\EInvoice\Models\Peppol\TaxCategoryType\ClassifiedTaxCategory;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use InvoiceNinja\EInvoice\Models\Peppol\CustomerPartyType\AccountingCustomerParty;
use InvoiceNinja\EInvoice\Models\Peppol\SupplierPartyType\AccountingSupplierParty;
use InvoiceNinja\EInvoice\Models\Peppol\PartyIdentificationType\PartyIdentification;
use InvoiceNinja\EInvoice\Models\Peppol\TaxCategoryType\TaxCategory;

/**
 * @test
 */
class FACT1Test extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestSkipped('prevent running in CI');

        $this->makeTestData();
    }

    public function testRoBuild()
    {
        $settings = $this->company->settings;
        $settings->currency_id = '42';
        $this->company->saveSettings($settings, $this->company);
        $this->company->save();

        $settings = ClientSettings::defaults();
        $settings->currency_id = '42';

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
            'settings' => $settings,
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

        $item = new InvoiceItem();
        $item->cost = 10;
        $item->quantity = 10;
        $item->tax_name1 = 'VAT';
        $item->tax_rate1 = '19';
        $item->product_key = "Product Name";
        $item->notes = "A great product description";

        $_invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'number' => 'INV-'.rand(1000, 1000000),
            'line_items' => [$item],
            'due_date' => now()->addDays(20)->format('Y-m-d'),
            'status_id' => 1,
            'discount' => 0,
        ]);

        $_invoice->service()->markSent()->save();
        $calc = $_invoice->calc();

        $invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();
        $invoice->UBLVersionID = '2.1';
        $invoice->CustomizationID = 'urn:cen.eu:en16931:2017#compliant#urn:efactura.mfinante.ro:CIUS-RO:1.0.1';
        $invoice->ID = $_invoice->number;
        $invoice->InvoiceTypeCode = 380;
        $invoice->IssueDate = new DateTime($_invoice->date);
        $invoice->DueDate = new DateTime($_invoice->due_date);
        $invoice->DocumentCurrencyCode = 'RON';
        $invoice->TaxCurrencyCode = 'RON';

        $asp = new AccountingSupplierParty();
        $party = new Party();

        $party_identification = new PartyIdentification();
        $party_identification->ID = 'company_id_number';
        $party->PartyIdentification[] = $party_identification;

        $sp_address = new PostalAddress();
        $sp_address->StreetName = $this->company->settings->address1;
        $sp_address->CityName = 'SECTOR2';
        $sp_address->CountrySubentity = 'RO-B';

        $country = new Country();
        $country->IdentificationCode = 'RO';
        $sp_address->Country = $country;

        $party->PostalAddress = $sp_address;

        $pts = new PartyTaxScheme();
        $tax_scheme = new TaxScheme();
        $tax_scheme->ID = 'VAT';

        $pts->CompanyID = 'RO234234234';
        $pts->TaxScheme = $tax_scheme;

        $party->PartyTaxScheme[] = $pts;

        $ple = new PartyLegalEntity();
        $ple->RegistrationName = $this->company->settings->name;
        $ple->CompanyID = 'J40/2222/2009';

        $party->PartyLegalEntity[] = $ple;

        $p_contact = new Contact();
        $p_contact->Name = $this->company->owner()->present()->name();
        $p_contact->Telephone = $this->company->settings->phone;
        $p_contact->ElectronicMail = $this->company->owner()->email;
        $party->Contact = $p_contact;
        $asp->Party = $party;

        $invoice->AccountingSupplierParty = $asp;

        $acp = new AccountingCustomerParty();

        $party = new Party();

        $party_identification = new PartyIdentification();
        $party_identification->ID = 'client_id_number';
        $party->PartyIdentification[] = $party_identification;

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

        $party->PartyLegalEntity[] = $ple;

        $p_contact = new Contact();
        $p_contact->Name = $client->contacts->first()->present()->name();
        $p_contact->Telephone = $client->contacts->first()->present()->phone();
        $p_contact->ElectronicMail = $client->contacts->first()->present()->email();

        $party->Contact = $p_contact;

        $acp->Party = $party;
        $invoice->AccountingCustomerParty = $acp;

        $taxtotal = new TaxTotal();
        $tax_amount = new TaxAmount();

        $tax_amount->amount = $calc->getItemTotalTaxes();
        $tax_amount->currencyID = $_invoice->client->currency()->code;

        $tc = new TaxCategory();
        $tc->ID = "S";

        $taxable = $this->getTaxable($_invoice);

        $taxable_amount = new TaxableAmount();
        $taxable_amount->amount = $taxable;
        $taxable_amount->currencyID = $_invoice->client->currency()->code;

        $tax_sub_total = new TaxSubtotal();
        $tax_sub_total->TaxAmount = $tax_amount;
        $tax_sub_total->TaxCategory = $tc;
        $tax_sub_total->TaxableAmount = $taxable_amount;
        $taxtotal->TaxSubtotal[] = $tax_sub_total;

        $invoice->TaxTotal[] = $taxtotal;

        $lmt = new LegalMonetaryTotal();

        $lea = new LineExtensionAmount();
        $lea->amount = $taxable;
        $lea->currencyID = $_invoice->client->currency()->code;

        $lmt->LineExtensionAmount = $lea;

        $tea = new TaxExclusiveAmount();
        $tea->amount = $taxable;
        $tea->currencyID = $_invoice->client->currency()->code;

        $lmt->TaxExclusiveAmount = $tea;

        $tia = new TaxInclusiveAmount();
        $tia->amount = $_invoice->amount;
        $tia->currencyID = $_invoice->client->currency()->code;

        $lmt->TaxInclusiveAmount = $tia;

        $pa = new PayableAmount();
        $pa->amount = $_invoice->amount;
        $pa->currencyID = $_invoice->client->currency()->code;

        $lmt->PayableAmount = $pa;
        $invoice->LegalMonetaryTotal = $lmt;

        foreach($_invoice->line_items as $key => $item) {

            $invoice_line = new InvoiceLine();
            $invoice_line->ID = $key++;

            $iq = new InvoicedQuantity();
            $iq->amount = $item->cost;
            $iq->unitCode = 'H87';

            $invoice_line->InvoicedQuantity = $iq;

            $invoice_line->Note = substr($item->notes, 0, 200);

            $ctc = new ClassifiedTaxCategory();
            $ctc->ID = 'S';

            $i = new Item();
            $i->Description = $item->notes;
            $i->Name = $item->product_key;

            $tax_scheme = new PeppolTaxScheme();
            $tax_scheme->ID = $item->tax_name1;
            $tax_scheme->Name = $item->tax_rate1;

            $ctc = new ClassifiedTaxCategory();
            $ctc->TaxScheme = $tax_scheme;
            $ctc->ID = 'S';

            $i->ClassifiedTaxCategory[] = $ctc;

            $invoice_line->Item = $i;


            $lea = new LineExtensionAmount();
            $lea->amount = $item->line_total;
            $lea->currencyID = $_invoice->client->currency()->code;

            $invoice_line->LineExtensionAmount = $lea;

            $price = new Price();
            $pa = new PriceAmount();
            $pa->amount = $item->line_total;
            $pa->currencyID = $_invoice->client->currency()->code;

            $price->PriceAmount = $pa;

            $lea = new LineExtensionAmount();
            $lea->amount = $item->line_total;
            $lea->currencyID = $_invoice->client->currency()->code;

            $invoice_line->LineExtensionAmount = $lea;

            $invoice->InvoiceLine[] = $invoice_line;
        }

        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $errors = $validator->validate($invoice);

        foreach($errors as $error) {
            // echo $error->getPropertyPath() . ': ' . $error->getMessage() . "\n";
        }

        $this->assertCount(0, $errors);

        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();
        // list of PropertyListExtractorInterface (any iterable)
        $listExtractors = [$reflectionExtractor];
        // list of PropertyTypeExtractorInterface (any iterable)
        $typeExtractors = [$reflectionExtractor,$phpDocExtractor];
        // list of PropertyDescriptionExtractorInterface (any iterable)
        $descriptionExtractors = [$phpDocExtractor];
        // list of PropertyAccessExtractorInterface (any iterable)
        $accessExtractors = [$reflectionExtractor];
        // list of PropertyInitializableExtractorInterface (any iterable)
        $propertyInitializableExtractors = [$reflectionExtractor];
        $propertyInfo = new PropertyInfoExtractor(
            // $listExtractors,
            $propertyInitializableExtractors,
            $descriptionExtractors,
            $typeExtractors,
            // $accessExtractors,
        );
        $context = [
            'xml_format_output' => true,
            'remove_empty_tags' => true,
        ];

        $encoder = new XmlEncoder($context);
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $metadataAwareNameConverter = new MetadataAwareNameConverter($classMetadataFactory);
        $discriminator = new ClassDiscriminatorFromClassMetadata($classMetadataFactory);

        $normalizer = new ObjectNormalizer($classMetadataFactory, $metadataAwareNameConverter, null, $propertyInfo);

        $normalizers = [  new DateTimeNormalizer(), $normalizer,  new ArrayDenormalizer() , ];
        $encoders = [$encoder, new JsonEncoder()];
        $serializer = new Serializer($normalizers, $encoders);

        $n_context = [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            // AbstractObjectNormalizer::SKIP_UNINITIALIZED_VALUES => true,
        ];


        // $invoice = $normalizer->normalize($invoice, 'json', $n_context);
        // echo print_r($invoice);
        // $invoice = $serializer->serialize($invoice, 'xml', $n_context);
        $dataxml = $serializer->encode($invoice, 'xml', $context);

        // echo $dataxml;

        //set default standard props
    }




    /**
     * @return float|int|mixed
     */
    private function getTaxable(Invoice $invoice): float
    {
        $total = 0;

        foreach ($invoice->line_items as $item) {
            $line_total = $item->quantity * $item->cost;

            if ($item->discount != 0) {
                if ($invoice->is_amount_discount) {
                    $line_total -= $item->discount;
                } else {
                    $line_total -= $line_total * $item->discount / 100;
                }
            }

            $total += $line_total;
        }

        if ($invoice->discount > 0) {
            if ($invoice->is_amount_discount) {
                $total -= $invoice->discount;
            } else {
                $total *= (100 - $invoice->discount) / 100;
                $total = round($total, 2);
            }
        }

        if ($invoice->custom_surcharge1 && $invoice->custom_surcharge_tax1) {
            $total += $invoice->custom_surcharge1;
        }

        if ($invoice->custom_surcharge2 && $invoice->custom_surcharge_tax2) {
            $total += $invoice->custom_surcharge2;
        }

        if ($invoice->custom_surcharge3 && $invoice->custom_surcharge_tax3) {
            $total += $invoice->custom_surcharge3;
        }

        if ($invoice->custom_surcharge4 && $invoice->custom_surcharge_tax4) {
            $total += $invoice->custom_surcharge4;
        }

        return $total;
    }


}
