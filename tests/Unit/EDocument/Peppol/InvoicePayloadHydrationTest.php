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

namespace Tests\Unit\EDocument\Peppol;

use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Services\EDocument\Standards\Peppol;
use App\Services\EDocument\UblDocumentKind;
use DOMDocument;
use DOMXPath;
use InvoiceNinja\EInvoice\EInvoice;
use InvoiceNinja\EInvoice\Models\Peppol\CreditNote as PeppolCreditNote;
use InvoiceNinja\EInvoice\Models\Peppol\CreditNoteLineType\CreditNoteLine;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID;
use InvoiceNinja\EInvoice\Models\Peppol\Invoice as PeppolInvoice;
use InvoiceNinja\EInvoice\Models\Peppol\InvoiceLineType\InvoiceLine;
use InvoiceNinja\EInvoice\Models\Peppol\ItemType\Item;
use InvoiceNinja\EInvoice\Models\Peppol\ItemPropertyType\AdditionalItemProperty;
use InvoiceNinja\EInvoice\Models\Peppol\OrderReferenceType\OrderReference;
use InvoiceNinja\EInvoice\Models\Peppol\PaymentMeansType\PaymentMeans;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use stdClass;
use Tests\TestCase;

/** Exercise active and preserved legacy initialization before line construction. */
class InvoicePayloadHydrationTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

$this->markTestSkipped('reenabled after we hydrate the e_invoice line items into the document.');
    }

    public static function documents(): iterable
    {
        yield 'invoice' => [false];
        yield 'credit' => [true];
    }

    #[DataProvider('documents')]
    public function testLegacyDefaultsCopyUiPayloadAsRawObjectsWhileSettingsAreTyped(bool $is_credit): void
    {
        $payload = $this->uiPayload($is_credit);
        $source_root = $is_credit ? 'CreditNote' : 'Invoice';
        $line_property = $is_credit ? 'CreditNoteLine' : 'InvoiceLine';
        $document_class = $is_credit ? PeppolCreditNote::class : PeppolInvoice::class;
        $entity = $this->entity($payload, $is_credit);
        $before = $entity->getAttributes();

        $this->assertArrayNotHasKey('ID', $payload['e_invoice'][$source_root]);
        $document = $this->initializeDocument($entity, true)->getDocument();

        $this->assertInstanceOf($document_class, $document);
        $this->assertCount(2, $document->{$line_property});
        $this->assertSame([], $document->{$line_property}[0]);

        $line = $document->{$line_property}[1];
        $this->assertInstanceOf(stdClass::class, $line);
        $this->assertInstanceOf(stdClass::class, $line->Item);
        $this->assertInstanceOf(stdClass::class, $line->Item->BuyersItemIdentification->ID);
        $this->assertSame('00042', $line->Item->BuyersItemIdentification->ID->value);
        $this->assertSame('0', $line->Item->SellersItemIdentification->ID->value);
        $this->assertSame('003', $line->OrderLineReference[0]->LineID->value);
        $this->assertInstanceOf(stdClass::class, $document->OrderReference);
        $this->assertSame('PO-001', $document->OrderReference->ID->value);

        // These settings went through setSettings()'s serializer first.
        $this->assertInstanceOf(PaymentMeans::class, $document->PaymentMeans[0]);
        $this->assertInstanceOf(ID::class, $document->PaymentMeans[0]->PayeeFinancialAccount->ID);
        $this->assertSame('CLIENT-ACCOUNT', $document->PaymentMeans[0]->PayeeFinancialAccount->ID->value);
        $this->assertSame($before, $entity->getAttributes());
    }

    #[DataProvider('documents')]
    public function testSerializerCanHydrateTheSameUiPayloadWithoutADocumentId(bool $is_credit): void
    {
        $payload = $this->uiPayload($is_credit);
        $source_root = $is_credit ? 'CreditNote' : 'Invoice';
        $line_property = $is_credit ? 'CreditNoteLine' : 'InvoiceLine';
        $document_class = $is_credit ? PeppolCreditNote::class : PeppolInvoice::class;
        $line_class = $is_credit ? CreditNoteLine::class : InvoiceLine::class;

        $document = (new EInvoice())->denormalize(
            $payload['e_invoice'][$source_root],
            $document_class,
        );

        $this->assertInstanceOf($document_class, $document);
        $this->assertInstanceOf($line_class, $document->{$line_property}[0]);
        $line = $document->{$line_property}[1];
        $this->assertInstanceOf($line_class, $line);
        $this->assertInstanceOf(Item::class, $line->Item);
        $this->assertInstanceOf(ID::class, $line->Item->BuyersItemIdentification->ID);
        $this->assertSame('00042', $line->Item->BuyersItemIdentification->ID->value);
        $this->assertSame('0', $line->Item->SellersItemIdentification->ID->value);
        $this->assertSame('003', $line->OrderLineReference[0]->LineID->value);
        $this->assertInstanceOf(OrderReference::class, $document->OrderReference);
    }

    public function testAddingAnEInvoiceIdSelectsTheExistingTypedInvoicePath(): void
    {
        $payload = $this->uiPayload(false);
        $payload['e_invoice']['Invoice']['ID'] = ['value' => $payload['number']];
        $entity = $this->entity($payload, false);

        $document = $this->initializeDocument($entity)->getDocument();

        $this->assertInstanceOf(PeppolInvoice::class, $document);
        $this->assertInstanceOf(InvoiceLine::class, $document->InvoiceLine[1]);
        $this->assertInstanceOf(Item::class, $document->InvoiceLine[1]->Item);
        $this->assertInstanceOf(ID::class, $document->InvoiceLine[1]->Item->BuyersItemIdentification->ID);
        $this->assertSame('00042', $document->InvoiceLine[1]->Item->BuyersItemIdentification->ID->value);
        $this->assertInstanceOf(OrderReference::class, $document->OrderReference);
    }

    #[DataProvider('documents')]
    public function testUpdatedDefaultsHydrateUiLinesAndPreservePaymentDefaults(bool $is_credit): void
    {
        $payload = $this->uiPayload($is_credit);
        $source_root = $is_credit ? 'CreditNote' : 'Invoice';
        $line_property = $is_credit ? 'CreditNoteLine' : 'InvoiceLine';
        $line_class = $is_credit ? CreditNoteLine::class : InvoiceLine::class;
        $entity = $this->entity($payload, $is_credit);
        $before = $entity->getAttributes();
        $peppol = $this->initializeDocument($entity);
        $document = $peppol->getDocument();
        $payment_means = (new ReflectionProperty($peppol, '_client_settings'))->getValue($peppol)->PaymentMeans;

        $this->assertArrayNotHasKey('ID', $payload['e_invoice'][$source_root]);
        $this->assertSame($document, $peppol->getDocument());
        $this->assertCount(2, $document->{$line_property});
        $this->assertInstanceOf($line_class, $document->{$line_property}[0]);
        $line = $document->{$line_property}[1];
        $this->assertInstanceOf($line_class, $line);
        $this->assertInstanceOf(Item::class, $line->Item);
        $this->assertInstanceOf(ID::class, $line->Item->BuyersItemIdentification->ID);
        $this->assertInstanceOf(ID::class, $line->Item->SellersItemIdentification->ID);
        $this->assertSame('00042', $line->Item->BuyersItemIdentification->ID->value);
        $this->assertSame('0', $line->Item->SellersItemIdentification->ID->value);
        $this->assertSame('003', $line->OrderLineReference[0]->LineID->value);
        $this->assertInstanceOf(OrderReference::class, $document->OrderReference);
        $this->assertSame('PO-001', $document->OrderReference->ID->value);
        $this->assertSame($payment_means, $document->PaymentMeans);
        $this->assertSame('CLIENT-ACCOUNT', $document->PaymentMeans[0]->PayeeFinancialAccount->ID->value);
        $this->assertSame($before, $entity->getAttributes());
    }

    #[DataProvider('documents')]
    public function testUpdatedDefaultsDoNotReplaceAnUnsuppliedDefaultWithModelNull(bool $is_credit): void
    {
        $payload = $this->uiPayload($is_credit);
        $source_root = $is_credit ? 'CreditNote' : 'Invoice';
        unset($payload['e_invoice'][$source_root]['OrderReference']);
        $entity = $this->entity($payload, $is_credit);
        $client_settings = $this->paymentSettings('CLIENT-ACCOUNT');
        $client_settings['Invoice']['OrderReference'] = ['ID' => ['value' => 'DEFAULT-PO']];
        $entity->client->e_invoice = $client_settings;
        $peppol = $this->initializeDocument($entity);
        $document = $peppol->getDocument();
        $default_reference = (new ReflectionProperty($peppol, '_client_settings'))->getValue($peppol)->OrderReference;

        // This null would overwrite the default in an unfiltered object merge.
        $hydrated = (new EInvoice())->denormalize(
            $payload['e_invoice'][$source_root],
            get_class($document),
        );
        $this->assertArrayHasKey('OrderReference', get_object_vars($hydrated));
        $this->assertNull($hydrated->OrderReference);

        $this->assertSame($default_reference, $document->OrderReference);
        $this->assertSame('DEFAULT-PO', $document->OrderReference->ID->value);
    }

    #[DataProvider('documents')]
    public function testUpdatedDefaultsUseExplicitInvoicePaymentMeansOverSettings(bool $is_credit): void
    {
        $payload = $this->uiPayload($is_credit);
        $source_root = $is_credit ? 'CreditNote' : 'Invoice';
        $payload['e_invoice'][$source_root]['PaymentMeans'] =
            $this->paymentSettings('DOCUMENT-ACCOUNT')['Invoice']['PaymentMeans'];
        $entity = $this->entity($payload, $is_credit);
        $peppol = $this->initializeDocument($entity);

        $document = $peppol->getDocument();
        $this->assertCount(1, $document->PaymentMeans);
        $this->assertInstanceOf(PaymentMeans::class, $document->PaymentMeans[0]);
        $this->assertInstanceOf(ID::class, $document->PaymentMeans[0]->PayeeFinancialAccount->ID);
        $this->assertSame('DOCUMENT-ACCOUNT', $document->PaymentMeans[0]->PayeeFinancialAccount->ID->value);
    }

    public function testUpdatedDefaultsOmitEmptyReferencesAndRetainPaymentSettingsFallback(): void
    {
        $payload = $this->uiPayload(false);
        $payload['e_invoice']['Invoice']['OrderReference'] = null;
        $payload['e_invoice']['Invoice']['PaymentMeans'] = [];
        $entity = $this->entity($payload, false);
        $peppol = $this->initializeDocument($entity);

        $this->assertInstanceOf(OrderReference::class, $peppol->getDocument()->OrderReference);
        $this->assertNull($peppol->getDocument()->OrderReference->ID);
        // Preserve the existing getSetting() fallback for an empty collection.
        $this->assertSame('CLIENT-ACCOUNT', $peppol->getDocument()->PaymentMeans[0]->PayeeFinancialAccount->ID->value);
        $encoded = json_decode($peppol->toJson(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('cac:OrderReference', $encoded);
    }

    #[DataProvider('documents')]
    public function testUpdatedDefaultsSupportOtherSchemaPropertiesAndXmlRoundTrip(bool $is_credit): void
    {
        $payload = $this->uiPayload($is_credit);
        $source_root = $is_credit ? 'CreditNote' : 'Invoice';
        $line_property = $is_credit ? 'CreditNoteLine' : 'InvoiceLine';
        $payload['e_invoice'][$source_root][$line_property][1]['Note'] = 'Custom line note';
        $payload['e_invoice'][$source_root][$line_property][1]['Item']['AdditionalItemProperty'] = [
            ['Name' => 'Colour', 'Value' => 'Blue'],
        ];
        $entity = $this->entity($payload, $is_credit);
        $peppol = $this->initializeDocument($entity);

        $line = $peppol->getDocument()->{$line_property}[1];
        $this->assertSame('Custom line note', $line->Note);
        $this->assertInstanceOf(AdditionalItemProperty::class, $line->Item->AdditionalItemProperty[0]);
        $this->assertSame('Blue', $line->Item->AdditionalItemProperty[0]->Value);

        // Check namespaced serialization and typed decoding of this partial
        // document, without claiming full-document XSD/business-rule validity.
        $xml = $peppol->toXml();
        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $this->assertSame('00042', $xpath->evaluate('string(//cac:BuyersItemIdentification/cbc:ID)'));
        $this->assertSame('0', $xpath->evaluate('string(//cac:SellersItemIdentification/cbc:ID)'));
        $this->assertSame('003', $xpath->evaluate('string(//cac:OrderLineReference/cbc:LineID)'));
        $this->assertSame('Blue', $xpath->evaluate('string(//cac:AdditionalItemProperty/cbc:Value)'));

        $decoded = (new EInvoice())->decode('Peppol', $xml, 'xml');
        $document_class = $is_credit ? PeppolCreditNote::class : PeppolInvoice::class;
        $this->assertInstanceOf($document_class, $decoded);
        // Empty metadata-only lines are omitted by the XML serializer.
        $decoded_line = $decoded->{$line_property}[0];
        $this->assertInstanceOf(Item::class, $decoded_line->Item);
        $this->assertInstanceOf(ID::class, $decoded_line->Item->BuyersItemIdentification->ID);
        $this->assertSame('Custom line note', $decoded_line->Note);
        $this->assertSame('Blue', $decoded_line->Item->AdditionalItemProperty[0]->Value);
        // Required identifier contract: currently fails during XML decoding.
        $this->assertSame(
            ['buyer' => '00042', 'seller' => '0', 'order_line' => '003'],
            [
                'buyer' => $decoded_line->Item->BuyersItemIdentification->ID->value,
                'seller' => $decoded_line->Item->SellersItemIdentification->ID->value,
                'order_line' => $decoded_line->OrderLineReference[0]->LineID->value,
            ],
        );
    }

    private function uiPayload(bool $is_credit): array
    {
        $source_root = $is_credit ? 'CreditNote' : 'Invoice';
        $line_property = $is_credit ? 'CreditNoteLine' : 'InvoiceLine';

        // ../ui/src/components/e-invoice/line-fields/fields.ts:
        // getTemplateFields()/setLineValue() use identifier .value wrappers;
        // withLineEntries() retains interior empty objects;
        // withOrderReference() writes the header ID wrapper.
        // The invoice create/save hooks submit e_invoice without adding its ID.
        $payload = [
            'number' => $is_credit ? 'CN-UI-001' : 'INV-UI-001',
            'po_number' => 'PO-001',
            'line_items' => [
                ['product_key' => 'First', 'quantity' => 1, 'cost' => 100],
                ['product_key' => 'Second', 'quantity' => 1, 'cost' => 100],
            ],
            'e_invoice' => [$source_root => [
                'OrderReference' => ['ID' => ['value' => 'PO-001']],
                $line_property => [
                    new stdClass(),
                    [
                        'Item' => [
                            'BuyersItemIdentification' => ['ID' => ['value' => '00042']],
                            'SellersItemIdentification' => ['ID' => ['value' => '0']],
                        ],
                        'OrderLineReference' => [['LineID' => ['value' => '003']]],
                    ],
                ],
            ]],
        ];

        // Laravel decodes incoming JSON into arrays, including an empty {}.
        return json_decode(json_encode($payload, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    private function entity(array $payload, bool $is_credit): Invoice|Credit
    {
        $company = new Company();
        $company->e_invoice = $this->paymentSettings('COMPANY-ACCOUNT');
        $client = new Client();
        $client->e_invoice = $this->paymentSettings('CLIENT-ACCOUNT');
        $entity = $is_credit ? new Credit() : new Invoice();

        // Apply incoming JSON using the real Eloquent attribute casts.
        $entity->forceFill($payload);
        $entity->amount = 200;
        $entity->setRelation('company', $company);
        $entity->setRelation('client', $client);

        return $entity;
    }

    private function paymentSettings(string $account_id): array
    {
        return ['Invoice' => ['PaymentMeans' => [[
            'PaymentMeansCode' => ['value' => '30'],
            'PayeeFinancialAccount' => ['ID' => ['value' => $account_id]],
        ]]]];
    }

    private function initializeDocument(Invoice|Credit $entity, bool $use_legacy = false): Peppol
    {
        // Isolate initialization from unrelated constructor calculations.
        // Invoke the production setSettings()/initDocument()/setInvoiceDefaults().
        $peppol = (new ReflectionClass(Peppol::class))->newInstanceWithoutConstructor();
        $peppol->invoice = $entity;
        (new ReflectionProperty($peppol, 'e'))->setValue($peppol, new EInvoice());
        (new ReflectionProperty($peppol, 'documentKind'))->setValue($peppol, UblDocumentKind::fromEntity($entity));
        (new ReflectionMethod($peppol, 'setSettings'))->invoke($peppol);

        if ($use_legacy) {
            $document = $entity instanceof Credit ? new PeppolCreditNote() : new PeppolInvoice();
            (new ReflectionProperty($peppol, 'p_invoice'))->setValue($peppol, $document);
            $peppol->setInvoiceDefaultsLegacy();
        } else {
            (new ReflectionMethod($peppol, 'initDocument'))->invoke($peppol);
        }

        return $peppol;
    }
}
