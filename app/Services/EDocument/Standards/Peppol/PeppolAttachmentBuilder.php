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

namespace App\Services\EDocument\Standards\Peppol;

use App\Models\Document;
use App\Models\Expense;
use App\Models\Task;
use App\Utils\Traits\MakesHash;
use App\Services\EDocument\Standards\Peppol;
use InvoiceNinja\EInvoice\Models\Peppol\AttachmentType\Attachment;
use InvoiceNinja\EInvoice\Models\Peppol\DocumentReferenceType\AdditionalDocumentReference;
use InvoiceNinja\EInvoice\Models\Peppol\EmbeddedDocumentBinaryObjectType\EmbeddedDocumentBinaryObject;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID;

class PeppolAttachmentBuilder
{
    use MakesHash;

    public function __construct(private Peppol $peppol)
    {
    }

    /**
     * Build an AdditionalDocumentReference from raw content.
     */
    public function buildDocumentReference(string $filename, string $content, string $mimeCode): AdditionalDocumentReference
    {
        $adr = new AdditionalDocumentReference();

        $id = new ID();
        $id->value = $filename;
        $adr->ID = $id;

        $attachment = new Attachment();
        $binary = new EmbeddedDocumentBinaryObject();
        $binary->value = base64_encode($content);
        $binary->mimeCode = $mimeCode;
        $binary->filename = str_replace(' ', '_', $filename);
        $attachment->EmbeddedDocumentBinaryObject = $binary;

        $adr->Attachment = $attachment;

        return $adr;
    }

    /**
     * Attach the invoice/credit PDF itself.
     */
    public function addPrimaryAttachment(): void
    {
        $invoice = $this->peppol->getInvoiceModel();
        $p_invoice = $this->peppol->getPeppolDocument();

        $filename = $invoice->getFileName();
        $pdf = $invoice instanceof \App\Models\Credit
            ? $invoice->service()->getCreditPdf($invoice->invitations->first())
            : $invoice->service()->getInvoicePdf();

        if (!isset($p_invoice->AdditionalDocumentReference)) {
            $p_invoice->AdditionalDocumentReference = [];
        }

        $p_invoice->AdditionalDocumentReference[] = $this->buildDocumentReference($filename, $pdf, 'application/pdf');
    }

    /**
     * Attach third-party documents from related entities.
     */
    public function addThirdPartyAttachments(): void
    {
        $invoice = $this->peppol->getInvoiceModel();
        $company = $this->peppol->getCompany();
        $maxSize = $this->peppol->max_attachment_size;

        if (!$company->account->hasFeature(\App\Models\Account::FEATURE_DOCUMENTS) || $invoice->client->getSetting('document_email_attachment') === false) {
            return;
        }

        // Recurring invoice documents
        if ($invoice->recurring_invoice()->exists()) {
            $this->attachDocumentsFrom($invoice->recurring_invoice->documents()->where('is_public', true)->cursor(), $maxSize);
        }

        // Invoice documents
        $this->attachDocumentsFrom($invoice->documents()->where('is_public', true)->cursor(), $maxSize);

        // Company documents
        $this->attachDocumentsFrom($invoice->company->documents()->where('is_public', true)->cursor(), $maxSize);

        // Expense and task documents from line items
        $this->attachLineItemDocuments($invoice, $maxSize);
    }

    /**
     * Attach a single third-party document if it passes validation.
     */
    private function attachDocument(Document $document): void
    {
        $file = $document->getFile();
        $mimeCode = $document->getMimeType();

        if (!$file || !in_array($mimeCode, ['application/pdf', 'application/xml'])) {
            return;
        }

        $p_invoice = $this->peppol->getPeppolDocument();
        $p_invoice->AdditionalDocumentReference[] = $this->buildDocumentReference($document->name, $file, $mimeCode);
    }

    /**
     * Iterate a cursor of documents, attaching those under the size limit.
     */
    private function attachDocumentsFrom(\Illuminate\Support\LazyCollection $documents, int $maxSize): void
    {
        $documents->each(function ($document) use ($maxSize) {
            /** @var Document $document */
            if ($document->size <= $maxSize) {
                $this->attachDocument($document);
            }
        });
    }

    /**
     * Attach documents from expenses and tasks referenced by line items.
     */
    private function attachLineItemDocuments($invoice, int $maxSize): void
    {
        foreach ($invoice->line_items as $item) {

            if (property_exists($item, 'expense_id') && $item->expense_id) {
                Expense::query()->whereIn('id', $this->transformKeys([$item->expense_id]))
                    ->where('invoice_documents', 1)
                    ->cursor()
                    ->each(function ($expense) use ($maxSize) {
                        $this->attachDocumentsFrom($expense->documents()->where('is_public', true)->cursor(), $maxSize);
                    });
            }

            if (property_exists($item, 'task_id') && $item->task_id && $invoice->company->invoice_task_documents) {
                Task::query()->whereIn('id', $this->transformKeys([$item->task_id]))
                    ->cursor()
                    ->each(function ($task) use ($maxSize) {
                        $this->attachDocumentsFrom($task->documents()->where('is_public', true)->cursor(), $maxSize);
                    });
            }
        }
    }
}
