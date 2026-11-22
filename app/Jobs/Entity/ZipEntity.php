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

namespace App\Jobs\Entity;

use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\DownloadCredits;
use App\Mail\DownloadDocuments;
use App\Mail\DownloadInvoices;
use App\Mail\DownloadPurchaseOrders;
use App\Mail\DownloadQuotes;
use App\Models\BaseModel;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\User;
use App\Services\Download\ArchiveWriter;
use App\Services\Download\TemporaryDownloadPublisher;
use App\Services\EDocument\UblDocumentKind;
use App\Services\EDocument\UblXmlEncoder;
use App\Utils\Ninja;
use App\Utils\TempFile;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\SavesDocuments;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use InvoiceNinja\EInvoice\EInvoice;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ZipEntity implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use MakesDates;
    use SavesDocuments;

    public $tries = 3;

    public $timeout = 21600;

    private string $entity_string = '';

    private string $archive = '';

    public bool $xml_only = false;

    /**
     * @param class-string<BaseModel> $entity_class
     */
    public function __construct(
        public mixed $entity_ids,
        public Company $company,
        public User $user,
        public string $entity_class,
        bool $xml_only = false,
    ) {
        $this->xml_only = $xml_only;
    }

    public function handle(ArchiveWriter $archive_writer, TemporaryDownloadPublisher $publisher): void
    {
        MultiDB::setDb($this->company->db);

        App::setLocale($this->company->locale());

        if ($this->entity_class === Document::class) {
            App::forgetInstance('translator');
            app('translator')->replace(Ninja::transformTranslations($this->company->settings));
        }

        $this->setEntityString()
                ->zipEntity($archive_writer)
                ->publish($publisher);
    }

    /**
     * setEntityString sets the entity_string property based on the entity class
     *
     * @return self
     */
    private function setEntityString(): self
    {

        $this->entity_string = match ($this->entity_class) {
            Invoice::class => 'invoices',
            Quote::class => 'quotes',
            Credit::class => 'credits',
            PurchaseOrder::class => 'purchase_orders',
            Document::class => 'documents',
            default => throw new InvalidArgumentException("Unsupported entity class [{$this->entity_class}]."),
        };

        return $this;

    }

    /**
     * getMailable returns the appropriate mailable for the entity class
     *
     * @param  string $url
     * @param  Company $company
     * @return \Illuminate\Contracts\Mail\Mailable
     */
    private function getMailable(string $url, Company $company): \Illuminate\Contracts\Mail\Mailable
    {

        return match ($this->entity_class) {
            Invoice::class => new DownloadInvoices($url, $company),
            Quote::class => new DownloadQuotes($url, $company),
            Credit::class => new DownloadCredits($url, $company),
            PurchaseOrder::class => new DownloadPurchaseOrders($url, $company),
            Document::class => new DownloadDocuments($url, $company),
            default => throw new InvalidArgumentException("Unsupported entity class [{$this->entity_class}]."),
        };

    }

    /**
     * getEntityString
     *
     * @return string
     */
    private function getEntityString(): string
    {
        return $this->entity_string;
    }

    /**
     * zipEntity builds the zip file and sets the archive property
     *
     * @param  ArchiveWriter $archive_writer
     * @return self
     */
    protected function zipEntity(ArchiveWriter $archive_writer): self
    {
        $query = $this->entity_class::withTrashed()
            ->where('company_id', $this->company->id)
            ->whereIn('id', $this->entity_ids);

        if ($this->entity_class === Document::class) {
            $entities = $query->with('documentable')->get();
        } elseif ($this->xml_only) {
            $entities = $query->get();
        } else {
            $entities = $query
                ->whereHas('invitations')
                ->with([
                    'invitations' => fn ($query) => $query
                        ->orderByDesc('signature_date')
                        ->orderByDesc('id'),
                ])
                ->get();
        }

        if ($entities->isEmpty()) {
            throw new RuntimeException('No eligible entities were found.');
        }

        $entries = [];
        $errors = [];

        foreach ($entities as $entity) {

            try {
                /** @var \App\Models\Invoice|\App\Models\Quote|\App\Models\Credit|\App\Models\PurchaseOrder|\App\Models\Document $entity */
                if ($entity instanceof Document) {
                    $contents = $this->readDocument($entity);

                    $entries[] = [
                        'contents' => $contents,
                        'file_name' => $this->documentFileName($entity),
                    ];

                    continue;
                }

                if ($this->xml_only) {
                    $entries[] = [
                        'contents' => $this->invoiceXml($entity),
                        'file_name' => $entity->getFileName('xml'),
                    ];

                    continue;
                }

                if ($this->shouldIncludeEDocument($entity)) {
                    try {
                        $entries[] = [
                            'contents' => $entity instanceof Invoice ? $this->invoiceXml($entity) : $this->generateXml($entity),
                            'file_name' => $entity->getFileName('xml'),
                        ];
                    } catch (Throwable $exception) {
                        nlog("could not create e invoice for {$entity->id}");
                        nlog($exception->getMessage());
                        $errors[] = "Could not export XML for {$entity->number}.";
                    }
                }

                $entries[] = [
                    'contents' => (new CreateRawPdf($entity->invitations->first()))->handle(),
                    'file_name' => $entity->getFileName(),
                ];
            } catch (Throwable $exception) {
                $name = $entity instanceof Document ? $entity->name : $entity->number;
                $errors[] = "Could not export [{$entity->id}] {$name}; the file could not be recovered.";
                nlog("ZipEntity:: unable to export [{$entity->id}]: " . $exception->getMessage());
            }
        }

        if ($errors) {
            $entries[] = [
                'file_name' => 'export_errors.txt',
                'contents' => implode("\n", $errors),
            ];
        }

        try {
            $this->archive = $archive_writer->write($entries);
        } catch (RuntimeException $exception) {
            nlog('ZipEntity:: could not make zip => ' . $exception->getMessage());

            $this->fail($exception);
            throw $exception;
        }

        return $this;
    }

    private function invoiceXml(Invoice $invoice): string
    {
        if ($invoice->backup->guid || $invoice->isSelfBilled()) {
            /** @var \App\Models\Document $document */
            $document = $invoice->documents()
                ->where(fn ($query) => $query->where('type', 'xml')->orWhere('name', 'like', '%.xml'))
                ->latest('id')
                ->first();

            if ($document) {
                return $this->readDocument($document);
            }

            $contents = $this->generateXml($invoice);
            $file = TempFile::UploadedFileFromRaw($contents, $invoice->getFileName('xml'), 'application/xml');
            $this->saveDocument($file, $invoice, true);

            return $contents;
        }

        return $this->generateXml($invoice);
    }

    private function readDocument(Document $document): string
    {
        try {
            $contents = $document->getFile();

            if (is_string($contents) && $contents !== '') {
                return $contents;
            }
        } catch (Throwable $exception) {
            nlog("ZipEntity:: unable to read document [{$document->id}]: " . $exception->getMessage());
        }

        if (Storage::disk($document->disk)->exists($document->url)) {
            throw new RuntimeException("Document [{$document->id}] exists but could not be read.");
        }

        $entity = $document->documentable;

        if (!($entity instanceof Invoice || $entity instanceof Quote || $entity instanceof Credit || $entity instanceof PurchaseOrder)
            || $entity->company_id !== $document->company_id) {
            throw new RuntimeException("Document [{$document->id}] has no source record from which it can be recreated.");
        }

        $contents = null;

        // Restore embedded attachments from their original data before generating a replacement.
        foreach (data_get($entity->e_invoice, 'Invoice.AdditionalDocumentReference', []) as $reference) {
            $binary = data_get($reference, 'Attachment.EmbeddedDocumentBinaryObject');

            if (data_get($binary, 'filename') === $document->name) {
                $contents = base64_decode((string) data_get($binary, 'value', ''), true);
                break;
            }
        }

        $extension = strtolower(pathinfo($document->name ?? '', PATHINFO_EXTENSION) ?: $document->type);

        if (!is_string($contents) || $contents === '') {
            if ($extension === 'xml') {
                $contents = $this->generateXml($entity);
            } elseif ($extension === 'pdf' && $document->name === $entity->getFileName('pdf')) {
                if (data_get($entity, 'sync.dn_completed') && $entity->getSignedPdfDocument()?->is($document)) {
                    throw new RuntimeException("The original signed PDF [{$document->id}] is unavailable.");
                }

                $invitation = $entity->invitations()->orderByDesc('signature_date')->orderByDesc('id')->first();

                if (!$invitation) {
                    $entity->service()->createInvitations()->save();
                    $invitation = $entity->invitations()->first();
                }

                $contents = (new CreateRawPdf($invitation))->handle();
            }
        }

        if (!is_string($contents) || $contents === '') {
            throw new RuntimeException("Document [{$document->id}] cannot be recreated from the available source data.");
        }

        if (!Storage::disk($document->disk)->put($document->url, $contents)) {
            throw new RuntimeException("Unable to restore document [{$document->id}] to storage.");
        }

        $document->size = strlen($contents);
        $document->saveQuietly();

        return $contents;
    }

    private function generateXml(Invoice|Quote|Credit|PurchaseOrder $entity): string
    {
        if ($entity instanceof Invoice && $entity->isSelfBilled()) {
            $encoder = new EInvoice();
            $document = $encoder->decode('Peppol', json_encode(data_get($entity->e_invoice, 'Invoice')), 'json');

            return UblXmlEncoder::wrap($encoder->encode($document, 'xml'), UblDocumentKind::Invoice);
        }

        return $entity->service()->getEDocument();
    }

    private function shouldIncludeEDocument(BaseModel $entity): bool
    {
        if ($entity instanceof Invoice || $entity instanceof Quote) {
            return $entity->client->getSetting('enable_e_invoice');
        }

        if ($entity instanceof PurchaseOrder) {
            return $entity->vendor->getSetting('enable_e_invoice');
        }

        return false;
    }

    /**
     * Gets the custom document entity filename to prevent naming conflicts.
     */
    private function documentFileName(Document $document): string
    {
        $date = $this->formatDate(Carbon::createFromTimestamp((int) $document->created_at), 'Y-m-d');
        $documentable = $document->documentable;
        $number = isset($documentable->number) ? "_{$documentable->number}" : '_';
        $entity = $documentable ? $documentable->translate_entity() : ctrans('texts.document');
        $file_name = (string) $document->name;

        if (pathinfo($file_name, PATHINFO_FILENAME) !== "{$entity}{$number}") {
            $file_name = "{$entity}{$number}_{$file_name}";
        }

        return "{$date}_{$file_name}";
    }

    /**
     * publish the zip file to the temporary download publisher and send the email
     *
     * @param  TemporaryDownloadPublisher $publisher
     * @return self
     */
    protected function publish(TemporaryDownloadPublisher $publisher): self
    {

        $entity_translation_string = $this->getEntityString();

        $file_name = date('Y-m-d-h-i-s') . '_' . str_replace(' ', '_', trans("texts.{$entity_translation_string}"))
            . ($this->xml_only ? '_xml' : '') . '.zip';

        $result = $publisher->publish(
            contents: $this->archive,
            storage_path: $this->company->file_path() . "downloads/{$file_name}",
            download_name: $file_name,
            expires_at: now()->addHours(12),
            user: $this->user,
        );

        $ninja_mailer_object = new NinjaMailerObject();
        $ninja_mailer_object->mailable = $this->getMailable($result->url, $this->company);
        $ninja_mailer_object->to_user = $this->user;
        $ninja_mailer_object->settings = $this->company->settings;
        $ninja_mailer_object->company = $this->company;

        NinjaMailerJob::dispatch($ninja_mailer_object);

        return $this;
    }

    public function failed(Throwable $exception): void
    {
        nlog('ZipEntity:: Exception:: => ' . $exception->getMessage());
        config(['queue.failed.driver' => null]);
    }
}
