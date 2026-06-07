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

namespace Tests\Integration\Einvoice;

use App\Models\Invoice;
use App\Services\EDocument\ZugferdPdfMerger;
use DateTimeImmutable;
use horstoeko\zugferd\codelists\ZugferdCountryCodes;
use horstoeko\zugferd\codelists\ZugferdCurrencyCodes;
use horstoeko\zugferd\codelists\ZugferdInvoiceType;
use horstoeko\zugferd\codelists\ZugferdUnitCodes;
use horstoeko\zugferd\codelists\ZugferdVatCategoryCodes;
use horstoeko\zugferd\codelists\ZugferdVatTypeCodes;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfReader;
use horstoeko\zugferd\ZugferdProfiles;
use Tests\TestCase;

class ZugferdPdfMergeTest extends TestCase
{
    public function testMergedFacturXPdfContainsRequiredXmpAndExtractableXml(): void
    {
        $pdf = $this->makeMerger('EN16931')->handle();

        $this->assertStringContainsString('<fx:DocumentType>INVOICE</fx:DocumentType>', $pdf);
        $this->assertStringContainsString('<fx:DocumentFileName>factur-x.xml</fx:DocumentFileName>', $pdf);
        $this->assertStringContainsString('<fx:Version>1.0</fx:Version>', $pdf);
        $this->assertStringContainsString('<fx:ConformanceLevel>EN 16931</fx:ConformanceLevel>', $pdf);
        $this->assertStringContainsString('<pdfaid:part>3</pdfaid:part>', $pdf);
        $this->assertStringContainsString('<pdfaid:conformance>B</pdfaid:conformance>', $pdf);
        $this->assertStringContainsString('/AFRelationship /Alternative', $pdf);

        $xml = ZugferdDocumentPdfReader::getXmlFromContent($pdf);

        $this->assertStringContainsString('CrossIndustryInvoice', $xml);

        $document = ZugferdDocumentPdfReader::readAndGuessFromContent($pdf);
        $document->getDocumentInformation($documentno, $documenttypecode, $documentdate, $documentcurrency, $taxcurrency, $taxname, $documentlanguage, $rest);

        $this->assertEquals('INV-2026-0001', $documentno);
    }

    public function testAttachmentRelationshipPolicyUsesAlternativeForFullInvoiceProfiles(): void
    {
        $this->assertSame('Alternative', ZugferdPdfMerger::attachmentRelationshipType('EN16931'));
        $this->assertSame('Alternative', ZugferdPdfMerger::attachmentRelationshipType('XInvoice_3_0'));
        $this->assertSame('Alternative', ZugferdPdfMerger::attachmentRelationshipType('XInvoice-Basic'));
        $this->assertSame('Data', ZugferdPdfMerger::attachmentRelationshipType('XInvoice-BasicWL'));
    }

    private function makeVisualPdf(): string
    {
        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->Text(10, 10, 'Invoice INV-2026-0001');

        return $pdf->Output('S');
    }

    private function makeMerger(string $profile): ZugferdPdfMerger
    {
        return new class (new Invoice(), $this->makeVisualPdf(), $profile, $this->makeDocument()) extends ZugferdPdfMerger {
            public function __construct(Invoice $invoice, string $pdf, ?string $profile, private ZugferdDocumentBuilder $document)
            {
                parent::__construct($invoice, $pdf, $profile);
            }

            protected function createDocument(): ZugferdDocumentBuilder
            {
                return $this->document;
            }
        };
    }

    private function makeDocument(): ZugferdDocumentBuilder
    {
        $document = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_EN16931);
        $invoiceDate = new DateTimeImmutable('2026-05-05');

        $document->setDocumentInformation('INV-2026-0001', ZugferdInvoiceType::INVOICE, $invoiceDate, ZugferdCurrencyCodes::EURO);
        $document->setDocumentSeller('Seller GmbH', '549910');
        $document->setDocumentSellerAddress('Seller Street 1', '', '', '80333', 'Munich', ZugferdCountryCodes::GERMANY);
        $document->addDocumentSellerVATRegistrationNumber('DE123456789');
        $document->setDocumentBuyer('Buyer AG', 'GE2020211');
        $document->setDocumentBuyerAddress('Buyer Street 15', '', '', '69876', 'Frankfurt', ZugferdCountryCodes::GERMANY);
        $document->setDocumentSupplyChainEvent($invoiceDate);
        $document->addNewPosition('1');
        $document->setDocumentPositionProductDetails('Consulting', '', 'CONSULTING-1');
        $document->setDocumentPositionNetPrice(100.0);
        $document->setDocumentPositionQuantity(1, ZugferdUnitCodes::REC20_PIECE);
        $document->addDocumentPositionTax(ZugferdVatCategoryCodes::STAN_RATE, ZugferdVatTypeCodes::VALUE_ADDED_TAX, 19.0);
        $document->setDocumentPositionLineSummation(100.0);
        $document->addDocumentTax(ZugferdVatCategoryCodes::STAN_RATE, ZugferdVatTypeCodes::VALUE_ADDED_TAX, 100.0, 19.0, 19.0);
        $document->setDocumentSummation(119.0, 119.0, 100.0, 0.0, 0.0, 100.0, 19.0);

        return $document;
    }
}
