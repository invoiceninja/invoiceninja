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

namespace Tests\Unit\EDocument;

use App\Services\EDocument\UblDocumentKind;
use App\Services\EDocument\UblXmlEncoder;
use InvoiceNinja\EInvoice\EInvoice;
use InvoiceNinja\EInvoice\Models\Peppol\CreditNote;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID;
use InvoiceNinja\EInvoice\Models\Peppol\Invoice;
use Tests\TestCase;

class UblXmlEncoderTest extends TestCase
{
    public function testWrapsEInvoiceInvoiceFragmentWithSingleRoot(): void
    {
        $encoded = (new EInvoice())->encode($this->peppolInvoiceWithNote('Path C:\new\Notes'), 'xml');
        $xml = UblXmlEncoder::wrap($encoded, UblDocumentKind::Invoice);

        $dom = $this->loadXml($xml);
        $root = $dom->documentElement;

        $this->assertSame('Invoice', $root->localName);
        $this->assertSame('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', $root->namespaceURI);
        $this->assertSame(1, $dom->getElementsByTagNameNS($root->namespaceURI, 'Invoice')->length);
        $this->assertSame('Path C:\new\Notes', $this->noteText($dom));
    }

    public function testWrapsEInvoiceCreditNoteFragmentWithSingleRoot(): void
    {
        $credit = new CreditNote();
        $id = new ID();
        $id->value = 'CN-1';
        $credit->ID = $id;
        $credit->Note = 'Path C:\new\Notes';

        $encoded = (new EInvoice())->encode($credit, 'xml');
        $xml = UblXmlEncoder::wrap($encoded, UblDocumentKind::CreditNote);

        $dom = $this->loadXml($xml);
        $root = $dom->documentElement;

        $this->assertSame('CreditNote', $root->localName);
        $this->assertSame('urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2', $root->namespaceURI);
        $this->assertSame(1, $dom->getElementsByTagNameNS($root->namespaceURI, 'CreditNote')->length);
        $this->assertSame('Path C:\new\Notes', $this->noteText($dom));
    }

    private function peppolInvoiceWithNote(string $note): Invoice
    {
        $invoice = new Invoice();
        $id = new ID();
        $id->value = 'INV-1';
        $invoice->ID = $id;
        $invoice->Note = $note;

        return $invoice;
    }

    private function loadXml(string $xml): \DOMDocument
    {
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml), 'Wrapped output must be well-formed XML');

        return $dom;
    }

    private function noteText(\DOMDocument $dom): string
    {
        $cbcNs = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
        $node = $dom->getElementsByTagNameNS($cbcNs, 'Note')->item(0);

        $this->assertNotNull($node, 'Expected cbc:Note in wrapped document');

        return $node->textContent;
    }
}
