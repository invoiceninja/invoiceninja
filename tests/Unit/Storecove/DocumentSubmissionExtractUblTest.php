<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit\Storecove;

use Tests\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Modules\Admin\Jobs\Storecove\DocumentSubmission;

class DocumentSubmissionExtractUblTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        if(!class_exists(DocumentSubmission::class)) {
            $this->markTestSkipped('DocumentSubmission class does not exist');
        }
    }
    /**
     * Test extracting CreditNote from StandardBusinessDocument wrapper
     */
    public function testExtractCreditNoteFromSbdWrapper(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><sh:StandardBusinessDocument xmlns:sh="http://www.unece.org/cefact/namespaces/StandardBusinessDocumentHeader"><sh:StandardBusinessDocumentHeader><sh:HeaderVersion>1.0</sh:HeaderVersion><sh:Sender><sh:Identifier Authority="iso6523-actorid-upis">0208:1234567890</sh:Identifier></sh:Sender><sh:Receiver><sh:Identifier Authority="iso6523-actorid-upis">0208:0987654321</sh:Identifier></sh:Receiver><sh:DocumentIdentification><sh:Standard>urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2</sh:Standard><sh:TypeVersion>2.1</sh:TypeVersion><sh:InstanceIdentifier>aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee</sh:InstanceIdentifier><sh:Type>CreditNote</sh:Type><sh:CreationDateAndTime>2026-01-22T15:53:41.44Z</sh:CreationDateAndTime></sh:DocumentIdentification><sh:BusinessScope><sh:Scope><sh:Type>DOCUMENTID</sh:Type><sh:InstanceIdentifier>urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2::CreditNote##urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0::2.1</sh:InstanceIdentifier><sh:Identifier>busdox-docid-qns</sh:Identifier></sh:Scope><sh:Scope><sh:Type>PROCESSID</sh:Type><sh:InstanceIdentifier>urn:fdc:peppol.eu:2017:poacc:billing:01:1.0</sh:InstanceIdentifier><sh:Identifier>cenbii-procid-ubl</sh:Identifier></sh:Scope><sh:Scope><sh:Type>COUNTRY_C1</sh:Type><sh:InstanceIdentifier>BE</sh:InstanceIdentifier></sh:Scope></sh:BusinessScope></sh:StandardBusinessDocumentHeader><CreditNote xmlns:cec="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2">
   <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
   <cbc:CustomizationID>urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0</cbc:CustomizationID>
   <cbc:ProfileID>urn:fdc:peppol.eu:2017:poacc:billing:01:1.0</cbc:ProfileID>
   <cbc:ID>TEST/2026/0001</cbc:ID>
   <cbc:IssueDate>2026-01-22</cbc:IssueDate>
   <cbc:CreditNoteTypeCode listAgencyID="6" listID="UNCL1001">381</cbc:CreditNoteTypeCode>
   <cbc:DocumentCurrencyCode listAgencyID="6" listID="ISO4217">EUR</cbc:DocumentCurrencyCode>
   <cac:OrderReference>
      <cbc:ID>TEST/2026/0001</cbc:ID>
   </cac:OrderReference>
   <cac:AdditionalDocumentReference>
      <cbc:ID>test20260001</cbc:ID>
   </cac:AdditionalDocumentReference>
   <cac:AccountingSupplierParty>
      <cac:Party>
         <cbc:EndpointID schemeID="0208">1234567890</cbc:EndpointID>
         <cac:PartyIdentification>
            <cbc:ID schemeAgencyID="ZZZ" schemeID="0208">1234567890</cbc:ID>
         </cac:PartyIdentification>
         <cac:PartyName>
            <cbc:Name>Test Supplier Company</cbc:Name>
         </cac:PartyName>
         <cac:PostalAddress>
            <cbc:StreetName>123 Test Street</cbc:StreetName>
            <cbc:CityName>Test City</cbc:CityName>
            <cbc:PostalZone>1000</cbc:PostalZone>
            <cbc:CountrySubentity>Test Region</cbc:CountrySubentity>
            <cac:Country>
               <cbc:IdentificationCode listAgencyID="6" listID="ISO3166-1:Alpha2">BE</cbc:IdentificationCode>
            </cac:Country>
         </cac:PostalAddress>
         <cac:PartyTaxScheme>
            <cbc:CompanyID schemeAgencyID="ZZZ" schemeID="9925">BE1234567890</cbc:CompanyID>
            <cac:TaxScheme>
               <cbc:ID schemeAgencyID="6" schemeID="UN/ECE 5153">VAT</cbc:ID>
            </cac:TaxScheme>
         </cac:PartyTaxScheme>
         <cac:PartyLegalEntity>
            <cbc:RegistrationName>Test Supplier Company</cbc:RegistrationName>
            <cbc:CompanyID schemeAgencyID="ZZZ" schemeID="0208">1234567890</cbc:CompanyID>
         </cac:PartyLegalEntity>
         <cac:Contact>
            <cbc:Name>Test Contact</cbc:Name>
            <cbc:ElectronicMail>supplier@example.com</cbc:ElectronicMail>
         </cac:Contact>
      </cac:Party>
   </cac:AccountingSupplierParty>
   <cac:AccountingCustomerParty>
      <cac:Party>
         <cbc:EndpointID schemeID="0208">0987654321</cbc:EndpointID>
         <cac:PartyName>
            <cbc:Name>Test Customer Company</cbc:Name>
         </cac:PartyName>
         <cac:PostalAddress>
            <cbc:StreetName>456 Customer Street</cbc:StreetName>
            <cbc:CityName>Customer City</cbc:CityName>
            <cbc:PostalZone>2000</cbc:PostalZone>
            <cac:Country>
               <cbc:IdentificationCode listAgencyID="6" listID="ISO3166-1:Alpha2">BE</cbc:IdentificationCode>
            </cac:Country>
         </cac:PostalAddress>
         <cac:PartyTaxScheme>
            <cbc:CompanyID schemeAgencyID="ZZZ" schemeID="9925">BE0987654321</cbc:CompanyID>
            <cac:TaxScheme>
               <cbc:ID schemeAgencyID="6" schemeID="UN/ECE 5153">VAT</cbc:ID>
            </cac:TaxScheme>
         </cac:PartyTaxScheme>
         <cac:PartyLegalEntity>
            <cbc:RegistrationName>Test Customer Company</cbc:RegistrationName>
         </cac:PartyLegalEntity>
         <cac:Contact>
            <cbc:Telephone>123456789</cbc:Telephone>
            <cbc:ElectronicMail>customer@example.com</cbc:ElectronicMail>
         </cac:Contact>
      </cac:Party>
   </cac:AccountingCustomerParty>
   <cac:PaymentMeans>
      <cbc:PaymentMeansCode>30</cbc:PaymentMeansCode>
      <cbc:PaymentID>TEST/2026/0001</cbc:PaymentID>
      <cac:PayeeFinancialAccount>
         <cbc:ID>BE12345678901234</cbc:ID>
         <cbc:Name>TEST SUPPLIER COMPANY</cbc:Name>
      </cac:PayeeFinancialAccount>
   </cac:PaymentMeans>
   <cac:TaxTotal>
      <cbc:TaxAmount currencyID="EUR">0.00</cbc:TaxAmount>
      <cac:TaxSubtotal>
         <cbc:TaxableAmount currencyID="EUR">49.50</cbc:TaxableAmount>
         <cbc:TaxAmount currencyID="EUR">0.00</cbc:TaxAmount>
         <cac:TaxCategory>
            <cbc:ID schemeAgencyID="6" schemeID="UNCL5305">E</cbc:ID>
            <cbc:Percent>0.0</cbc:Percent>
            <cbc:TaxExemptionReason>Exempt</cbc:TaxExemptionReason>
            <cac:TaxScheme>
               <cbc:ID schemeAgencyID="6" schemeID="UN/ECE 5153">VAT</cbc:ID>
            </cac:TaxScheme>
         </cac:TaxCategory>
      </cac:TaxSubtotal>
   </cac:TaxTotal>
   <cac:LegalMonetaryTotal>
      <cbc:LineExtensionAmount currencyID="EUR">49.50</cbc:LineExtensionAmount>
      <cbc:TaxExclusiveAmount currencyID="EUR">49.50</cbc:TaxExclusiveAmount>
      <cbc:TaxInclusiveAmount currencyID="EUR">49.50</cbc:TaxInclusiveAmount>
      <cbc:PayableAmount currencyID="EUR">49.50</cbc:PayableAmount>
   </cac:LegalMonetaryTotal>
   <cac:CreditNoteLine>
      <cbc:ID>1</cbc:ID>
      <cbc:CreditedQuantity unitCode="C62" unitCodeListID="UNECERec20">1.000000</cbc:CreditedQuantity>
      <cbc:LineExtensionAmount currencyID="EUR">22.00</cbc:LineExtensionAmount>
      <cac:Item>
         <cbc:Description>Credit note on TEST/2025/0001</cbc:Description>
         <cbc:Name>DOMAIN .COM/.NET/.ORG</cbc:Name>
         <cac:ClassifiedTaxCategory>
            <cbc:ID schemeAgencyID="6" schemeID="UNCL5305">E</cbc:ID>
            <cbc:Percent>0.0</cbc:Percent>
            <cac:TaxScheme>
               <cbc:ID schemeAgencyID="6" schemeID="UN/ECE 5153">VAT</cbc:ID>
            </cac:TaxScheme>
         </cac:ClassifiedTaxCategory>
      </cac:Item>
      <cac:Price>
         <cbc:PriceAmount currencyID="EUR">22.00</cbc:PriceAmount>
         <cbc:BaseQuantity unitCode="C62" unitCodeListID="UNECERec20">1.0</cbc:BaseQuantity>
      </cac:Price>
   </cac:CreditNoteLine>
   <cac:CreditNoteLine>
      <cbc:ID>2</cbc:ID>
      <cbc:CreditedQuantity unitCode="C62" unitCodeListID="UNECERec20">0.250000</cbc:CreditedQuantity>
      <cbc:LineExtensionAmount currencyID="EUR">27.50</cbc:LineExtensionAmount>
      <cac:Item>
         <cbc:Description>Credit note on TEST/2025/0001</cbc:Description>
         <cbc:Name>PRESTATION DE SERVICES</cbc:Name>
         <cac:ClassifiedTaxCategory>
            <cbc:ID schemeAgencyID="6" schemeID="UNCL5305">E</cbc:ID>
            <cbc:Percent>0.0</cbc:Percent>
            <cac:TaxScheme>
               <cbc:ID schemeAgencyID="6" schemeID="UN/ECE 5153">VAT</cbc:ID>
            </cac:TaxScheme>
         </cac:ClassifiedTaxCategory>
      </cac:Item>
      <cac:Price>
         <cbc:PriceAmount currencyID="EUR">110.00</cbc:PriceAmount>
         <cbc:BaseQuantity unitCode="C62" unitCodeListID="UNECERec20">1.0</cbc:BaseQuantity>
      </cac:Price>
   </cac:CreditNoteLine>
</CreditNote></sh:StandardBusinessDocument>';

        $job = new DocumentSubmission([]);
        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('extractInvoiceUbl');
        $method->setAccessible(true);

        $result = $method->invoke($job, $xml);

        // Assert that the result is valid XML
        $this->assertNotEmpty($result);

        // Assert that the result contains CreditNote
        $this->assertStringContainsString('<CreditNote', $result);
        $this->assertStringContainsString('TEST/2026/0001', $result);

        // Assert that the result does NOT contain the SBD wrapper
        $this->assertStringNotContainsString('StandardBusinessDocument', $result);
        $this->assertStringNotContainsString('StandardBusinessDocumentHeader', $result);

        // Assert that the result is valid XML that can be parsed
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($result), 'Extracted XML should be valid');

        // Assert that the root element is CreditNote
        $this->assertEquals('CreditNote', $dom->documentElement->localName);
        $this->assertEquals('urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2', $dom->documentElement->namespaceURI);
    }

    /**
     * Test extracting Invoice from StandardBusinessDocument wrapper
     */
    public function testExtractInvoiceFromSbdWrapper(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><sh:StandardBusinessDocument xmlns:sh="http://www.unece.org/cefact/namespaces/StandardBusinessDocumentHeader"><sh:StandardBusinessDocumentHeader><sh:HeaderVersion>1.0</sh:HeaderVersion><sh:Sender><sh:Identifier Authority="iso6523-actorid-upis">0208:0769867026</sh:Identifier></sh:Sender><sh:Receiver><sh:Identifier Authority="iso6523-actorid-upis">0208:0821894064</sh:Identifier></sh:Receiver><sh:DocumentIdentification><sh:Standard>urn:oasis:names:specification:ubl:schema:xsd:Invoice-2</sh:Standard><sh:TypeVersion>2.1</sh:TypeVersion><sh:InstanceIdentifier>507dcfe6-7f6e-473a-bd20-f1c8dce2e2c8</sh:InstanceIdentifier><sh:Type>Invoice</sh:Type><sh:CreationDateAndTime>2026-01-22T15:53:41.44Z</sh:CreationDateAndTime></sh:DocumentIdentification></sh:StandardBusinessDocumentHeader><Invoice xmlns:cec="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">
   <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
   <cbc:CustomizationID>urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0</cbc:CustomizationID>
   <cbc:ProfileID>urn:fdc:peppol.eu:2017:poacc:billing:01:1.0</cbc:ProfileID>
   <cbc:ID>INV/2026/0001</cbc:ID>
   <cbc:IssueDate>2026-01-22</cbc:IssueDate>
   <cbc:DocumentCurrencyCode listAgencyID="6" listID="ISO4217">EUR</cbc:DocumentCurrencyCode>
</Invoice></sh:StandardBusinessDocument>';

        $job = new DocumentSubmission([]);
        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('extractInvoiceUbl');
        $method->setAccessible(true);

        $result = $method->invoke($job, $xml);

        // Assert that the result is valid XML
        $this->assertNotEmpty($result);

        // Assert that the result contains Invoice
        $this->assertStringContainsString('<Invoice', $result);
        $this->assertStringContainsString('INV/2026/0001', $result);

        // Assert that the result does NOT contain the SBD wrapper
        $this->assertStringNotContainsString('StandardBusinessDocument', $result);

        // Assert that the result is valid XML that can be parsed
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($result), 'Extracted XML should be valid');

        // Assert that the root element is Invoice
        $this->assertEquals('Invoice', $dom->documentElement->localName);
        $this->assertEquals('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', $dom->documentElement->namespaceURI);
    }

    /**
     * Test that exception is thrown when neither Invoice nor CreditNote is found
     */
    public function testThrowsExceptionWhenNoInvoiceOrCreditNoteFound(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><sh:StandardBusinessDocument xmlns:sh="http://www.unece.org/cefact/namespaces/StandardBusinessDocumentHeader"><sh:StandardBusinessDocumentHeader><sh:HeaderVersion>1.0</sh:HeaderVersion></sh:StandardBusinessDocumentHeader><OtherDocument xmlns="urn:example:other:document"><SomeElement>Test</SomeElement></OtherDocument></sh:StandardBusinessDocument>';

        $job = new DocumentSubmission([]);
        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('extractInvoiceUbl');
        $method->setAccessible(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No Invoice or CreditNote tag found in XML');

        $method->invoke($job, $xml);
    }

    /**
     * Test that method handles XML without SBD wrapper (direct Invoice)
     */
    public function testExtractDirectInvoiceWithoutWrapper(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><Invoice xmlns:cec="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">
   <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
   <cbc:ID>DIRECT/2026/0001</cbc:ID>
   <cbc:IssueDate>2026-01-22</cbc:IssueDate>
</Invoice>';

        $job = new DocumentSubmission([]);
        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('extractInvoiceUbl');
        $method->setAccessible(true);

        $result = $method->invoke($job, $xml);

        // Assert that the result is valid XML
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('<Invoice', $result);
        $this->assertStringContainsString('DIRECT/2026/0001', $result);

        // Assert that the result is valid XML that can be parsed
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($result), 'Extracted XML should be valid');
        $this->assertEquals('Invoice', $dom->documentElement->localName);
    }

    /**
     * Test that method handles XML without SBD wrapper (direct CreditNote)
     */
    public function testExtractDirectCreditNoteWithoutWrapper(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><CreditNote xmlns:cec="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2">
   <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
   <cbc:ID>CN/2026/0001</cbc:ID>
   <cbc:IssueDate>2026-01-22</cbc:IssueDate>
</CreditNote>';

        $job = new DocumentSubmission([]);
        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('extractInvoiceUbl');
        $method->setAccessible(true);

        $result = $method->invoke($job, $xml);

        // Assert that the result is valid XML
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('<CreditNote', $result);
        $this->assertStringContainsString('CN/2026/0001', $result);

        // Assert that the result is valid XML that can be parsed
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($result), 'Extracted XML should be valid');
        $this->assertEquals('CreditNote', $dom->documentElement->localName);
    }
}
