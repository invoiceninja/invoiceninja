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

namespace Tests\Unit\Storecove;

use App\Models\Invoice;
use App\Services\EDocument\Gateway\Storecove\Models\Credit as StorecoveCredit;
use App\Services\EDocument\Gateway\Storecove\Models\Invoice as StorecoveInvoice;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Services\EDocument\Gateway\Storecove\StorecoveAdapter;
use App\Services\EDocument\UblDocumentKind;
use App\Services\EDocument\UblXmlEncoder;
use InvoiceNinja\EInvoice\EInvoice;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\LineExtensionAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AttachmentType\Attachment;
use InvoiceNinja\EInvoice\Models\Peppol\CodeType\PaymentMeansCode;
use InvoiceNinja\EInvoice\Models\Peppol\CreditNote;
use InvoiceNinja\EInvoice\Models\Peppol\CreditNoteLineType\CreditNoteLine;
use InvoiceNinja\EInvoice\Models\Peppol\DocumentReferenceType\AdditionalDocumentReference;
use InvoiceNinja\EInvoice\Models\Peppol\EmbeddedDocumentBinaryObjectType\EmbeddedDocumentBinaryObject;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID;
use InvoiceNinja\EInvoice\Models\Peppol\Invoice as PeppolInvoice;
use InvoiceNinja\EInvoice\Models\Peppol\InvoiceLineType\InvoiceLine;
use InvoiceNinja\EInvoice\Models\Peppol\PaymentMeansType\PaymentMeans;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Tests\TestCase;

class StorecoveAdapterAttachmentLimitTest extends TestCase
{
    public function testInvoicePayloadCapsAdditionalDocumentReferences(): void
    {
        $this->assertAttachmentCap(UblDocumentKind::Invoice, 12);
    }

    public function testCreditPayloadCapsAdditionalDocumentReferences(): void
    {
        $this->assertAttachmentCap(UblDocumentKind::CreditNote, 12);
    }

    public function testAttachmentCountAtOrBelowTheCapIsUnchanged(): void
    {
        $cap = $this->attachmentCap();

        $this->assertAttachmentCap(UblDocumentKind::Invoice, $cap);
        $this->assertAttachmentCap(UblDocumentKind::CreditNote, 1);
    }

    private function assertAttachmentCap(UblDocumentKind $kind, int $count): void
    {
        $cap = $this->attachmentCap();
        $expected = min($count, $cap);
        $filenames = $this->filenames($count);

        $peppol = $kind->isCreditNote() ? new CreditNote() : new PeppolInvoice();
        $peppol->PaymentMeans = [$this->paymentMeans()];
        if ($kind->isCreditNote()) {
            $peppol->CreditNoteLine = [$this->creditNoteLine()];
        } else {
            $peppol->InvoiceLine = [$this->invoiceLine()];
        }
        $peppol->AdditionalDocumentReference = array_map(
            fn (string $filename) => $this->documentReference($filename),
            $filenames,
        );

        $e = new EInvoice();
        $xml = UblXmlEncoder::wrap($e->encode($peppol, 'xml'), $kind);

        $adapter = new StorecoveAdapter(new Storecove());
        $adapter->transformFromPeppol(new Invoice(), $peppol, $kind, $xml);

        $initialized = (new ReflectionClass($adapter))->getProperty('storecove_invoice')->isInitialized($adapter);
        $this->assertTrue($initialized, 'transformFromPeppol did not build a Storecove model: ' . json_encode($adapter->getErrors()));

        $model = $adapter->getInvoice();
        $this->assertInstanceOf(
            $kind->isCreditNote() ? StorecoveCredit::class : StorecoveInvoice::class,
            $model,
        );

        $modelAttachments = $model->getAttachments() ?? [];
        $this->assertCount($expected, $modelAttachments, 'Storecove model attachments were not capped.');
        $this->assertSame($filenames[0], $modelAttachments[0]->getFilename());
        $this->assertSame($filenames[$expected - 1], $modelAttachments[$expected - 1]->getFilename());

        $document = $this->encodeDocument($adapter);

        $this->assertArrayHasKey('attachments', $document);
        $this->assertArrayNotHasKey('cac:AdditionalDocumentReference', $document);

        $wireAttachments = $document['attachments'];
        $this->assertCount($expected, $wireAttachments);
        $this->assertSame($filenames[0], $wireAttachments[0]['filename'] ?? null, 'First wire attachment: ' . json_encode($wireAttachments[0] ?? null));
        $this->assertSame($filenames[$expected - 1], $wireAttachments[$expected - 1]['filename'] ?? null);
        $this->assertSame(base64_encode($filenames[0]), $wireAttachments[0]['document'] ?? null);

        if ($count > $cap) {
            $dropped = $filenames[$cap];
            $encoded = json_encode($wireAttachments);
            $this->assertStringNotContainsString($dropped, (string) $encoded);
        }
    }

    /**
     * @return list<string>
     */
    private function filenames(int $count): array
    {
        $filenames = ['invoice.pdf'];

        for ($i = 1; $i < $count; $i++) {
            $filenames[] = "extra-{$i}.pdf";
        }

        return $filenames;
    }

    private function invoiceLine(): InvoiceLine
    {
        $id = new ID();
        $id->value = '1';

        $amount = new LineExtensionAmount();
        $amount->amount = '1.00';
        $amount->currencyID = 'EUR';

        $line = new InvoiceLine();
        $line->ID = $id;
        $line->LineExtensionAmount = $amount;

        return $line;
    }

    private function creditNoteLine(): CreditNoteLine
    {
        $id = new ID();
        $id->value = '1';

        $amount = new LineExtensionAmount();
        $amount->amount = '1.00';
        $amount->currencyID = 'EUR';

        $line = new CreditNoteLine();
        $line->ID = $id;
        $line->LineExtensionAmount = $amount;

        return $line;
    }

    private function paymentMeans(): PaymentMeans
    {
        $code = new PaymentMeansCode();
        $code->value = '30';

        $means = new PaymentMeans();
        $means->PaymentMeansCode = $code;

        return $means;
    }

    private function documentReference(string $filename): AdditionalDocumentReference
    {
        $id = new ID();
        $id->value = $filename;

        $binary = new EmbeddedDocumentBinaryObject();
        $binary->value = base64_encode($filename);
        $binary->mimeCode = 'application/pdf';
        $binary->filename = $filename;

        $attachment = new Attachment();
        $attachment->EmbeddedDocumentBinaryObject = $binary;

        $reference = new AdditionalDocumentReference();
        $reference->ID = $id;
        $reference->Attachment = $attachment;

        return $reference;
    }

    private function attachmentCap(): int
    {
        return (new ReflectionClass(StorecoveAdapter::class))
            ->getReflectionConstant('MAX_ATTACHMENTS')
            ->getValue();
    }

    /**
     * @return array<string, mixed>
     */
    private function encodeDocument(StorecoveAdapter $adapter): array
    {
        $method = new ReflectionMethod(StorecoveAdapter::class, 'getSerializer');
        $serializer = $method->invoke($adapter);

        $encoded = $serializer->encode($adapter->getInvoice(), 'json', [
            DateTimeNormalizer::FORMAT_KEY => 'Y-m-d',
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ]);

        return json_decode($encoded, true);
    }
}
