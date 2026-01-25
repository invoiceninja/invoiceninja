<?php

namespace Tests\Feature\EInvoice;

use Tests\TestCase;
use App\Services\EDocument\Standards\Validation\XsltDocumentValidator;

class PeppolXmlValidationTest extends TestCase
{

private string $xml = '<?xml version="1.0" encoding="UTF-8"?>
  <Invoice xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
      xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
      xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">
    <cbc:CustomizationID>urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0</cbc:CustomizationID>
    <cbc:ProfileID>urn:fdc:peppol.eu:2017:poacc:billing:01:1.0</cbc:ProfileID>
    <cbc:ID>INV-20xx-0001</cbc:ID>
    <cbc:IssueDate>2026-01-23</cbc:IssueDate>
    <cbc:DueDate>2026-02-23</cbc:DueDate>
    <cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>
    <cbc:Note>Autoliquidation Following art.</cbc:Note>
    <cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>
    <cbc:BuyerReference>REF-12345/001/0001</cbc:BuyerReference>
    <cac:OrderReference>
      <cbc:ID>REF-12345/001/0001</cbc:ID>
    </cac:OrderReference>
    <cac:AdditionalDocumentReference>
      <cbc:ID>Invoice_INV-20xx-0001.pdf</cbc:ID>
    </cac:AdditionalDocumentReference>
    <cac:AccountingSupplierParty>
      <cac:Party>
        <cbc:EndpointID schemeID="0037">BE0123456789</cbc:EndpointID>
        <cac:PartyIdentification>
          <cbc:ID schemeID="0037">BE0123456789</cbc:ID>
        </cac:PartyIdentification>
        <cac:PartyName>
          <cbc:Name>Example Company S.A.</cbc:Name>
        </cac:PartyName>
        <cac:PostalAddress>
          <cbc:StreetName>Example Street 123</cbc:StreetName>
          <cbc:CityName>Brussels</cbc:CityName>
          <cbc:PostalZone>1000</cbc:PostalZone>
          <cac:Country>
            <cbc:IdentificationCode>BE</cbc:IdentificationCode>
          </cac:Country>
        </cac:PostalAddress>
        <cac:PartyTaxScheme>
          <cbc:CompanyID>BE0123456789</cbc:CompanyID>
          <cac:TaxScheme>
            <cbc:ID>VAT</cbc:ID>
          </cac:TaxScheme>
        </cac:PartyTaxScheme>
        <cac:PartyLegalEntity>
          <cbc:RegistrationName>Example Company S.A.</cbc:RegistrationName>
        </cac:PartyLegalEntity>
        <cac:Contact>
          <cbc:Name>John Doe</cbc:Name>
          <cbc:Telephone>+31 2 123 45 67</cbc:Telephone>
          <cbc:ElectronicMail>contact@example.com</cbc:ElectronicMail>
        </cac:Contact>
      </cac:Party>
    </cac:AccountingSupplierParty>
    <cac:AccountingCustomerParty>
      <cac:Party>
        <cbc:EndpointID schemeID="0037">987654321</cbc:EndpointID>
        <cac:PartyIdentification>
          <cbc:ID schemeID="0037">987654321</cbc:ID>
        </cac:PartyIdentification>
        <cac:PartyName>
          <cbc:Name>Customer Company GmbH</cbc:Name>
        </cac:PartyName>
        <cac:PostalAddress>
          <cbc:StreetName>Customer Street 456</cbc:StreetName>
          <cbc:CityName>Berlin</cbc:CityName>
          <cbc:PostalZone>10115</cbc:PostalZone>
          <cac:Country>
            <cbc:IdentificationCode>DE</cbc:IdentificationCode>
          </cac:Country>
        </cac:PostalAddress>
        <cac:PartyTaxScheme>
          <cbc:CompanyID>DE987654321</cbc:CompanyID>
          <cac:TaxScheme>
            <cbc:ID>VAT</cbc:ID>
          </cac:TaxScheme>
        </cac:PartyTaxScheme>
        <cac:PartyLegalEntity>
          <cbc:RegistrationName>Customer Company GmbH</cbc:RegistrationName>
        </cac:PartyLegalEntity>
        <cac:Contact>
          <cbc:ElectronicMail>contact@customer.com</cbc:ElectronicMail>
        </cac:Contact>
      </cac:Party>
    </cac:AccountingCustomerParty>
    <cac:Delivery>
      <cbc:ActualDeliveryDate>2026-01-21</cbc:ActualDeliveryDate>
      <cac:DeliveryLocation>
        <cac:Address>
          <cac:Country>
            <cbc:IdentificationCode>BE</cbc:IdentificationCode>
          </cac:Country>
        </cac:Address>
      </cac:DeliveryLocation>
    </cac:Delivery>
    <cac:PaymentMeans>
      <cbc:PaymentMeansCode>1</cbc:PaymentMeansCode>
    </cac:PaymentMeans>
    <cac:PaymentTerms>
      <cbc:Note>30 Days</cbc:Note>
    </cac:PaymentTerms>
    <cac:TaxTotal>
      <cbc:TaxAmount currencyID="EUR">0</cbc:TaxAmount>
      <cac:TaxSubtotal>
        <cbc:TaxableAmount currencyID="EUR">10000</cbc:TaxableAmount>
        <cbc:TaxAmount currencyID="EUR">0</cbc:TaxAmount>
        <cac:TaxCategory>
          <cbc:ID>K</cbc:ID>
          <cbc:Percent>0</cbc:Percent>
          <cbc:TaxExemptionReasonCode>vatex-eu-ic</cbc:TaxExemptionReasonCode>
          <cbc:TaxExemptionReason>Intra-Community supply</cbc:TaxExemptionReason>
          <cac:TaxScheme>
            <cbc:ID>VAT</cbc:ID>
          </cac:TaxScheme>
        </cac:TaxCategory>
      </cac:TaxSubtotal>
    </cac:TaxTotal>
    <cac:LegalMonetaryTotal>
      <cbc:LineExtensionAmount currencyID="EUR">10000</cbc:LineExtensionAmount>
      <cbc:TaxExclusiveAmount currencyID="EUR">10000</cbc:TaxExclusiveAmount>
      <cbc:TaxInclusiveAmount currencyID="EUR">10000</cbc:TaxInclusiveAmount>
      <cbc:AllowanceTotalAmount currencyID="EUR">0</cbc:AllowanceTotalAmount>
      <cbc:ChargeTotalAmount currencyID="EUR">0</cbc:ChargeTotalAmount>
      <cbc:PayableAmount currencyID="EUR">10000</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
    <cac:InvoiceLine>
      <cbc:ID>1</cbc:ID>
      <cbc:InvoicedQuantity unitCode="C62">10</cbc:InvoicedQuantity>
      <cbc:LineExtensionAmount currencyID="EUR">1000</cbc:LineExtensionAmount>
      <cac:Item>
        <cbc:Description>Service Support Package A</cbc:Description>
        <cbc:Name>SVC-001</cbc:Name>
        <cac:ClassifiedTaxCategory>
          <cbc:ID>K</cbc:ID>
          <cbc:Percent>0</cbc:Percent>
          <cac:TaxScheme>
            <cbc:ID>VAT</cbc:ID>
          </cac:TaxScheme>
        </cac:ClassifiedTaxCategory>
      </cac:Item>
      <cac:Price>
        <cbc:PriceAmount currencyID="EUR">100</cbc:PriceAmount>
      </cac:Price>
    </cac:InvoiceLine>
    <cac:InvoiceLine>
      <cbc:ID>2</cbc:ID>
      <cbc:InvoicedQuantity unitCode="C62">5</cbc:InvoicedQuantity>
      <cbc:LineExtensionAmount currencyID="EUR">500</cbc:LineExtensionAmount>
      <cac:Item>
        <cbc:Description>Service Support Package B</cbc:Description>
        <cbc:Name>SVC-002</cbc:Name>
        <cac:ClassifiedTaxCategory>
          <cbc:ID>K</cbc:ID>
          <cbc:Percent>0</cbc:Percent>
          <cac:TaxScheme>
            <cbc:ID>VAT</cbc:ID>
          </cac:TaxScheme>
        </cac:ClassifiedTaxCategory>
      </cac:Item>
      <cac:Price>
        <cbc:PriceAmount currencyID="EUR">100</cbc:PriceAmount>
      </cac:Price>
    </cac:InvoiceLine>
    <cac:InvoiceLine>
      <cbc:ID>3</cbc:ID>
      <cbc:InvoicedQuantity unitCode="C62">20</cbc:InvoicedQuantity>
      <cbc:LineExtensionAmount currencyID="EUR">2000</cbc:LineExtensionAmount>
      <cac:Item>
        <cbc:Description>Service Support Package C</cbc:Description>
        <cbc:Name>SVC-003</cbc:Name>
        <cac:ClassifiedTaxCategory>
          <cbc:ID>K</cbc:ID>
          <cbc:Percent>0</cbc:Percent>
          <cac:TaxScheme>
            <cbc:ID>VAT</cbc:ID>
          </cac:TaxScheme>
        </cac:ClassifiedTaxCategory>
      </cac:Item>
      <cac:Price>
        <cbc:PriceAmount currencyID="EUR">100</cbc:PriceAmount>
      </cac:Price>
    </cac:InvoiceLine>
    <cac:InvoiceLine>
      <cbc:ID>4</cbc:ID>
      <cbc:InvoicedQuantity unitCode="C62">8</cbc:InvoicedQuantity>
      <cbc:LineExtensionAmount currencyID="EUR">800</cbc:LineExtensionAmount>
      <cac:Item>
        <cbc:Description>Service Support D</cbc:Description>
        <cbc:Name>SVC-004</cbc:Name>
        <cac:ClassifiedTaxCategory>
          <cbc:ID>K</cbc:ID>
          <cbc:Percent>0</cbc:Percent>
          <cac:TaxScheme>
            <cbc:ID>VAT</cbc:ID>
          </cac:TaxScheme>
        </cac:ClassifiedTaxCategory>
      </cac:Item>
      <cac:Price>
        <cbc:PriceAmount currencyID="EUR">100</cbc:PriceAmount>
      </cac:Price>
    </cac:InvoiceLine>
    <cac:InvoiceLine>
      <cbc:ID>5</cbc:ID>
      <cbc:InvoicedQuantity unitCode="C62">8</cbc:InvoicedQuantity>
      <cbc:LineExtensionAmount currencyID="EUR">800</cbc:LineExtensionAmount>
      <cac:Item>
        <cbc:Description>Service Support Package E</cbc:Description>
        <cbc:Name>SVC-005</cbc:Name>
        <cac:ClassifiedTaxCategory>
          <cbc:ID>K</cbc:ID>
          <cbc:Percent>0</cbc:Percent>
          <cac:TaxScheme>
            <cbc:ID>VAT</cbc:ID>
          </cac:TaxScheme>
        </cac:ClassifiedTaxCategory>
      </cac:Item>
      <cac:Price>
        <cbc:PriceAmount currencyID="EUR">100</cbc:PriceAmount>
      </cac:Price>
    </cac:InvoiceLine>
    <cac:InvoiceLine>
      <cbc:ID>6</cbc:ID>
      <cbc:InvoicedQuantity unitCode="C62">2</cbc:InvoicedQuantity>
      <cbc:LineExtensionAmount currencyID="EUR">1000</cbc:LineExtensionAmount>
      <cac:Item>
        <cbc:Description>Software License A</cbc:Description>
        <cbc:Name>SW-001</cbc:Name>
        <cac:ClassifiedTaxCategory>
          <cbc:ID>K</cbc:ID>
          <cbc:Percent>0</cbc:Percent>
          <cac:TaxScheme>
            <cbc:ID>VAT</cbc:ID>
          </cac:TaxScheme>
        </cac:ClassifiedTaxCategory>
      </cac:Item>
      <cac:Price>
        <cbc:PriceAmount currencyID="EUR">500</cbc:PriceAmount>
      </cac:Price>
    </cac:InvoiceLine>
    <cac:InvoiceLine>
      <cbc:ID>7</cbc:ID>
      <cbc:InvoicedQuantity unitCode="C62">2</cbc:InvoicedQuantity>
      <cbc:LineExtensionAmount currencyID="EUR">1000</cbc:LineExtensionAmount>
      <cac:Item>
        <cbc:Description>Software License B</cbc:Description>
        <cbc:Name>SW-002</cbc:Name>
        <cac:ClassifiedTaxCategory>
          <cbc:ID>K</cbc:ID>
          <cbc:Percent>0</cbc:Percent>
          <cac:TaxScheme>
            <cbc:ID>VAT</cbc:ID>
          </cac:TaxScheme>
        </cac:ClassifiedTaxCategory>
      </cac:Item>
      <cac:Price>
        <cbc:PriceAmount currencyID="EUR">500</cbc:PriceAmount>
      </cac:Price>
    </cac:InvoiceLine>
    <cac:InvoiceLine>
      <cbc:ID>8</cbc:ID>
      <cbc:InvoicedQuantity unitCode="C62">5</cbc:InvoicedQuantity>
      <cbc:LineExtensionAmount currencyID="EUR">1000</cbc:LineExtensionAmount>
      <cac:Item>
        <cbc:Description>Software License C</cbc:Description>
        <cbc:Name>SW-003</cbc:Name>
        <cac:ClassifiedTaxCategory>
          <cbc:ID>K</cbc:ID>
          <cbc:Percent>0</cbc:Percent>
          <cac:TaxScheme>
            <cbc:ID>VAT</cbc:ID>
          </cac:TaxScheme>
        </cac:ClassifiedTaxCategory>
      </cac:Item>
      <cac:Price>
        <cbc:PriceAmount currencyID="EUR">200</cbc:PriceAmount>
      </cac:Price>
    </cac:InvoiceLine>
    <cac:InvoiceLine>
      <cbc:ID>9</cbc:ID>
      <cbc:InvoicedQuantity unitCode="C62">10</cbc:InvoicedQuantity>
      <cbc:LineExtensionAmount currencyID="EUR">500</cbc:LineExtensionAmount>
      <cac:Item>
        <cbc:Description>Additional Service Package</cbc:Description>
        <cbc:Name>SVC-006</cbc:Name>
        <cac:ClassifiedTaxCategory>
          <cbc:ID>K</cbc:ID>
          <cbc:Percent>0</cbc:Percent>
          <cac:TaxScheme>
            <cbc:ID>VAT</cbc:ID>
          </cac:TaxScheme>
        </cac:ClassifiedTaxCategory>
      </cac:Item>
      <cac:Price>
        <cbc:PriceAmount currencyID="EUR">50</cbc:PriceAmount>
      </cac:Price>
    </cac:InvoiceLine>
    <cac:InvoiceLine>
      <cbc:ID>10</cbc:ID>
      <cbc:InvoicedQuantity unitCode="C62">2</cbc:InvoicedQuantity>
      <cbc:LineExtensionAmount currencyID="EUR">500</cbc:LineExtensionAmount>
      <cac:Item>
        <cbc:Description>Additional Feature Package</cbc:Description>
        <cbc:Name>SVC-007</cbc:Name>
        <cac:ClassifiedTaxCategory>
          <cbc:ID>K</cbc:ID>
          <cbc:Percent>0</cbc:Percent>
          <cac:TaxScheme>
            <cbc:ID>VAT</cbc:ID>
          </cac:TaxScheme>
        </cac:ClassifiedTaxCategory>
      </cac:Item>
      <cac:Price>
        <cbc:PriceAmount currencyID="EUR">250</cbc:PriceAmount>
      </cac:Price>
    </cac:InvoiceLine>
    <cac:InvoiceLine>
      <cbc:ID>11</cbc:ID>
      <cbc:InvoicedQuantity unitCode="C62">3</cbc:InvoicedQuantity>
      <cbc:LineExtensionAmount currencyID="EUR">900</cbc:LineExtensionAmount>
      <cac:Item>
        <cbc:Description>Professional Services - Remote</cbc:Description>
        <cbc:Name>PS-001</cbc:Name>
        <cac:ClassifiedTaxCategory>
          <cbc:ID>K</cbc:ID>
          <cbc:Percent>0</cbc:Percent>
          <cac:TaxScheme>
            <cbc:ID>VAT</cbc:ID>
          </cac:TaxScheme>
        </cac:ClassifiedTaxCategory>
      </cac:Item>
      <cac:Price>
        <cbc:PriceAmount currencyID="EUR">300</cbc:PriceAmount>
      </cac:Price>
    </cac:InvoiceLine>
  </Invoice>
';


public function setUp(): void
{
    parent::setUp();

    try {
        $processor = new \Saxon\SaxonProcessor();
    } catch (\Throwable $e) {
        $this->markTestSkipped('saxon not installed');
    }

}

public function testPeppolXmlValidation()
{
        
        try {
            $processor = new \Saxon\SaxonProcessor();
        } catch (\Throwable $e) {
            $this->markTestSkipped('saxon not installed');
        }

        $validator = new XsltDocumentValidator($this->xml);
        $validator->validate();

        if (count($validator->getErrors()) > 0) {
            // nlog($this->xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());
    }
}