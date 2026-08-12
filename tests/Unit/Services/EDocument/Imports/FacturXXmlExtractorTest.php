<?php

namespace Tests\Unit\Services\EDocument\Imports;

use DateTimeImmutable;
use App\Services\EDocument\Imports\FacturXXmlExtractor;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use horstoeko\zugferd\codelists\ZugferdCountryCodes;
use horstoeko\zugferd\codelists\ZugferdCurrencyCodes;
use horstoeko\zugferd\codelists\ZugferdInvoiceType;
use horstoeko\zugferd\codelists\ZugferdUnitCodes;
use horstoeko\zugferd\codelists\ZugferdVatCategoryCodes;
use horstoeko\zugferd\codelists\ZugferdVatTypeCodes;
use horstoeko\zugferd\exception\ZugferdNoPdfAttachmentFoundException;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfMerger;
use horstoeko\zugferd\ZugferdProfiles;
use Modules\Admin\Jobs\Storecove\ReceiveDocument;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use UnexpectedValueException;

class FacturXXmlExtractorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if(!class_exists(Modules\Admin\Jobs\Storecove\ReceiveDocument::class)) {
            $this->markTestSkipped('Test cannot run in CI/CD environment.');
        }
    }

    public function test_extracts_and_parses_cii_payload_from_factur_x_pdf(): void
    {
        $xml = $this->makeCiiXml();
        $pdf = $this->makeFacturXPdf($xml);

        $extractor = new FacturXXmlExtractor();
        $extractedXml = $extractor->extract($pdf);

        $this->assertTrue($extractor->isPdf($pdf));
        $this->assertXmlStringEqualsXmlString($xml, $extractedXml);

        $document = $extractor->read($extractedXml);
        $document->getDocumentInformation($documentNumber, $documentTypeCode, $documentDate, $documentCurrency, $taxCurrency, $documentName, $documentLanguage, $specifiedPeriod);

        $this->assertSame('FACTUR-X-2026-0001', $documentNumber);
        $this->assertSame(ZugferdInvoiceType::INVOICE, $documentTypeCode);
        $this->assertSame('EUR', $documentCurrency);
    }

    public function test_accepts_standalone_factur_x_cii_xml(): void
    {
        $xml = $this->makeCiiXml();
        $extractor = new FacturXXmlExtractor();

        $this->assertFalse($extractor->isPdf($xml));
        $this->assertSame($xml, $extractor->extract($xml));
    }

    public function test_rejects_pdf_without_factur_x_attachment(): void
    {
        $this->expectException(ZugferdNoPdfAttachmentFoundException::class);

        (new FacturXXmlExtractor())->extract($this->makeVisualPdf());
    }

    public function test_rejects_non_cii_xml(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('does not contain a Factur-X CII invoice payload');

        (new FacturXXmlExtractor())->extract(
            '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/>'
        );
    }

    public function test_received_document_extracts_xml_and_preserves_original_factur_x_pdf(): void
    {
        $xml = $this->makeCiiXml();
        $pdf = $this->makeFacturXPdf($xml);
        $storecove = $this->createMock(Storecove::class);
        $storecove->expects($this->once())
            ->method('getDocument')
            ->with('received-document-guid', 'original')
            ->willReturn([
                'guid' => 'received-document-guid',
                'original' => base64_encode($pdf),
            ]);

        $job = new ReceiveDocument([
            'event_group' => 'invoice',
            'tenant_id' => 'company-key',
            'document_guid' => 'received-document-guid',
        ]);

        $reflection = new ReflectionClass($job);
        $storecoveProperty = $reflection->getProperty('storecove');
        $storecoveProperty->setValue($job, $storecove);
        $parseOriginal = $reflection->getMethod('parseOriginal');
        $parseOriginal->invoke($job);

        $xmlProperty = $reflection->getProperty('original_base64_xml');
        $documentProperty = $reflection->getProperty('original_base64_document');
        $mimeTypeProperty = $reflection->getProperty('original_document_mime_type');
        $htmlProperty = $reflection->getProperty('html');

        $this->assertXmlStringEqualsXmlString($xml, base64_decode($xmlProperty->getValue($job)));
        $this->assertSame($pdf, base64_decode($documentProperty->getValue($job)));
        $this->assertSame('application/pdf', $mimeTypeProperty->getValue($job));
        $this->assertSame('', $htmlProperty->getValue($job));
    }

    public function test_received_document_renders_and_sanitizes_standalone_factur_x_xml(): void
    {
        $xml = str_replace(
            'Acheteur SAS',
            '&lt;img src=x onerror=alert(1)&gt;',
            $this->makeCiiXml()
        );
        $storecove = $this->createMock(Storecove::class);
        $storecove->expects($this->once())
            ->method('getDocument')
            ->with('received-document-guid', 'original')
            ->willReturn([
                'guid' => 'received-document-guid',
                'original' => base64_encode($xml),
            ]);

        $job = new ReceiveDocument([
            'event_group' => 'invoice',
            'tenant_id' => 'company-key',
            'document_guid' => 'received-document-guid',
        ]);

        $reflection = new ReflectionClass($job);
        $reflection->getProperty('storecove')->setValue($job, $storecove);
        $reflection->getMethod('parseOriginal')->invoke($job);

        $html = $reflection->getProperty('html')->getValue($job);

        $this->assertNotSame('', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('alert(', $html);
        $this->assertSame('', $reflection->getProperty('original_base64_document')->getValue($job));
        $this->assertSame('', $reflection->getProperty('original_document_mime_type')->getValue($job));
    }

    public function test_reads_french_extended_ctc_profile_without_changing_extracted_xml(): void
    {
        $extendedXml = $this->makeCiiXml(ZugferdProfiles::PROFILE_EXTENDED);
        $frenchCtcXml = str_replace(
            'urn:cen.eu:en16931:2017#conformant#urn:factur-x.eu:1p0:extended',
            'urn:cen.eu:en16931:2017#conformant#urn.cpro.gouv.fr:1p0:extended-ctc-fr',
            $extendedXml
        );
        $extractor = new FacturXXmlExtractor();

        $this->assertSame($frenchCtcXml, $extractor->extract($frenchCtcXml));

        $document = $extractor->read($frenchCtcXml);
        $document->getDocumentInformation($documentNumber, $documentTypeCode, $documentDate, $documentCurrency, $taxCurrency, $documentName, $documentLanguage, $specifiedPeriod);

        $this->assertSame('FACTUR-X-2026-0001', $documentNumber);
        $this->assertSame(ZugferdProfiles::PROFILE_EXTENDED, $document->getProfileId());
    }

    public function test_extracts_and_parses_all_factur_x_pdf_fixtures(): void
    {
        $expectedDocuments = [
            'Avoir_FR_type381_BASIC.pdf' => [
                'profile' => ZugferdProfiles::PROFILE_BASIC,
                'number' => 'AV-2017-0005',
                'type' => ZugferdInvoiceType::CREDITNOTE,
                'date' => '2017-11-16',
                'currency' => ZugferdCurrencyCodes::EURO,
                'seller' => 'Au bon moulin',
                'buyer' => 'Ma jolie boutique',
                'grand_total' => 233.47,
                'due' => 233.47,
                'tax_total' => 14.99,
            ],
            'Facture_DOM_BASICWL.pdf' => [
                'profile' => ZugferdProfiles::PROFILE_BASICWL,
                'number' => 'FA-2017-0009',
                'type' => ZugferdInvoiceType::INVOICE,
                'date' => '2017-11-05',
                'currency' => ZugferdCurrencyCodes::EURO,
                'seller' => 'Au bon moulin',
                'buyer' => 'Hôtel Saint Denis',
                'grand_total' => 530.75,
                'due' => 383.75,
                'tax_total' => 0.0,
            ],
            'Facture_DOM_MINIMUM.pdf' => [
                'profile' => ZugferdProfiles::PROFILE_MINIMUM,
                'number' => 'FA-2017-0009',
                'type' => ZugferdInvoiceType::INVOICE,
                'date' => '2017-11-05',
                'currency' => ZugferdCurrencyCodes::EURO,
                'seller' => 'Au bon moulin',
                'buyer' => 'Hôtel Saint Denis',
                'grand_total' => 530.75,
                'due' => 383.75,
                'tax_total' => 0.0,
            ],
            'Facture_FR_BASICWL.pdf' => [
                'profile' => ZugferdProfiles::PROFILE_BASICWL,
                'number' => 'FA-2017-0010',
                'type' => ZugferdInvoiceType::INVOICE,
                'date' => '2017-11-13',
                'currency' => ZugferdCurrencyCodes::EURO,
                'seller' => 'Au bon moulin',
                'buyer' => 'Ma jolie boutique',
                'grand_total' => 671.15,
                'due' => 470.15,
                'tax_total' => 46.25,
            ],
            'Facture_FR_MINIMUM.pdf' => [
                'profile' => ZugferdProfiles::PROFILE_MINIMUM,
                'number' => 'FA-2017-0010',
                'type' => ZugferdInvoiceType::INVOICE,
                'date' => '2017-11-13',
                'currency' => ZugferdCurrencyCodes::EURO,
                'seller' => 'Au bon moulin',
                'buyer' => 'Ma jolie boutique',
                'grand_total' => 671.15,
                'due' => 470.15,
                'tax_total' => 46.25,
            ],
            'Facture_UE_BASICWL.pdf' => [
                'profile' => ZugferdProfiles::PROFILE_BASICWL,
                'number' => 'FA-2017-0008',
                'type' => ZugferdInvoiceType::INVOICE,
                'date' => '2017-11-03',
                'currency' => ZugferdCurrencyCodes::EURO,
                'seller' => 'Au bon moulin',
                'buyer' => 'Me gusta olive',
                'grand_total' => 2076.76,
                'due' => 1453.76,
                'tax_total' => 0.0,
            ],
            'Facture_UE_MINIMUM.pdf' => [
                'profile' => ZugferdProfiles::PROFILE_MINIMUM,
                'number' => 'FA-2017-0008',
                'type' => ZugferdInvoiceType::INVOICE,
                'date' => '2017-11-03',
                'currency' => ZugferdCurrencyCodes::EURO,
                'seller' => 'Au bon moulin',
                'buyer' => 'Me gusta olive',
                'grand_total' => 2076.76,
                'due' => 1453.76,
                'tax_total' => 0.0,
            ],
            'facturx-en16931-exemple-facture.pdf' => [
                'profile' => ZugferdProfiles::PROFILE_EN16931,
                'number' => 'FAC-2026-0342',
                'type' => ZugferdInvoiceType::INVOICE,
                'date' => '2026-06-10',
                'currency' => ZugferdCurrencyCodes::EURO,
                'seller' => 'Dupont Services SARL',
                'buyer' => 'Martin Industries SAS',
                'grand_total' => 2789.15,
                'due' => 2789.15,
                'tax_total' => 449.15,
            ],
        ];
        $pdfFiles = glob(__DIR__ . '/*.pdf') ?: [];
        $fixtureNames = array_map('basename', $pdfFiles);
        $expectedFixtureNames = array_keys($expectedDocuments);
        sort($fixtureNames);
        sort($expectedFixtureNames);

        $this->assertSame($expectedFixtureNames, $fixtureNames, 'Every PDF fixture must have document property expectations.');

        $extractor = new FacturXXmlExtractor();

        foreach ($pdfFiles as $pdfFile) {
            $fileName = basename($pdfFile);
            $pdf = file_get_contents($pdfFile);
            $this->assertNotFalse($pdf, "Unable to read {$fileName}.");

            $xml = $extractor->extract($pdf);
            $document = $extractor->read($xml);
            $document->getDocumentInformation($documentNumber, $documentTypeCode, $documentDate, $documentCurrency, $taxCurrency, $documentName, $documentLanguage, $specifiedPeriod);
            $document->getDocumentSeller($sellerName, $sellerIds, $sellerDescription);
            $document->getDocumentBuyer($buyerName, $buyerIds, $buyerDescription);
            $document->getDocumentSummation($grandTotal, $duePayable, $lineTotal, $chargeTotal, $allowanceTotal, $taxBasisTotal, $taxTotal, $roundingAmount, $prepaidAmount);

            $expected = $expectedDocuments[$fileName];
            $this->assertSame($expected['profile'], $document->getProfileId(), $fileName);
            $this->assertSame($expected['number'], $documentNumber, $fileName);
            $this->assertSame($expected['type'], $documentTypeCode, $fileName);
            $this->assertSame($expected['date'], $documentDate?->format('Y-m-d'), $fileName);
            $this->assertSame($expected['currency'], $documentCurrency, $fileName);
            $this->assertSame($expected['seller'], $sellerName, $fileName);
            $this->assertSame($expected['buyer'], $buyerName, $fileName);
            $this->assertEqualsWithDelta($expected['grand_total'], $grandTotal, 0.001, $fileName);
            $this->assertEqualsWithDelta($expected['due'], $duePayable, 0.001, $fileName);
            $this->assertEqualsWithDelta($expected['tax_total'], $taxTotal, 0.001, $fileName);
        }
    }

    private function makeFacturXPdf(string $xml): string
    {
        return (new ZugferdDocumentPdfMerger($xml, $this->makeVisualPdf()))
            ->generateDocument()
            ->downloadString();
    }

    private function makeVisualPdf(): string
    {
        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->Text(10, 10, 'Factur-X invoice FACTUR-X-2026-0001');

        return $pdf->Output('S');
    }

    private function makeCiiXml(int $profile = ZugferdProfiles::PROFILE_EN16931): string
    {
        $invoiceDate = new DateTimeImmutable('2026-08-11');
        $document = ZugferdDocumentBuilder::createNew($profile);
        $document->setDocumentInformation('FACTUR-X-2026-0001', ZugferdInvoiceType::INVOICE, $invoiceDate, ZugferdCurrencyCodes::EURO);
        $document->setDocumentSeller('Fournisseur SAS', 'FR-SUPPLIER');
        $document->setDocumentSellerAddress('1 Rue de Paris', '', '', '75001', 'Paris', ZugferdCountryCodes::FRANCE);
        $document->addDocumentSellerVATRegistrationNumber('FR40303265045');
        $document->setDocumentBuyer('Acheteur SAS', 'FR-BUYER');
        $document->setDocumentBuyerAddress('2 Rue de Lyon', '', '', '69001', 'Lyon', ZugferdCountryCodes::FRANCE);
        $document->setDocumentSupplyChainEvent($invoiceDate);
        $document->addNewPosition('1');
        $document->setDocumentPositionProductDetails('Conseil', '', 'CONSULTING-1');
        $document->setDocumentPositionNetPrice(100.0);
        $document->setDocumentPositionQuantity(1, ZugferdUnitCodes::REC20_PIECE);
        $document->addDocumentPositionTax(ZugferdVatCategoryCodes::STAN_RATE, ZugferdVatTypeCodes::VALUE_ADDED_TAX, 20.0);
        $document->setDocumentPositionLineSummation(100.0);
        $document->addDocumentTax(ZugferdVatCategoryCodes::STAN_RATE, ZugferdVatTypeCodes::VALUE_ADDED_TAX, 100.0, 20.0, 20.0);
        $document->setDocumentSummation(120.0, 120.0, 100.0, 0.0, 0.0, 100.0, 20.0);

        return $document->getContent();
    }
}
