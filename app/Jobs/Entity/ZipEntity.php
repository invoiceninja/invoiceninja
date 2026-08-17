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
use App\Utils\Ninja;
use App\Utils\Traits\MakesDates;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
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

    public $tries = 3;

    public $timeout = 10800;

    private string $entity_string = '';

    private string $archive = '';

    /**
     * @param class-string<BaseModel> $entity_class
     */
    public function __construct(
        public mixed $entity_ids,
        public Company $company,
        public User $user,
        public string $entity_class,
    ) {}

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

        foreach ($entities as $entity) {

            /** @var \App\Models\Invoice|\App\Models\Quote|\App\Models\Credit|\App\Models\PurchaseOrder|\App\Models\Document $entity */
            if ($entity instanceof Document) {
                $contents = $entity->getFile();

                if (! is_string($contents)) {
                    throw new RuntimeException("Unable to read document [{$entity->id}].");
                }

                $entries[] = [
                    'contents' => $contents,
                    'file_name' => $this->documentFileName($entity),
                ];

                continue;
            }

            if ($this->shouldIncludeEDocument($entity)) {
                try {
                    $entries[] = [
                        'contents' => $entity->service()->getEDocument(),
                        'file_name' => $entity->getFileName('xml'),
                    ];
                } catch (Throwable $exception) {
                    nlog("could not create e invoice for {$entity->id}");
                    nlog($exception->getMessage());
                }
            }

            $entries[] = [
                'contents' => (new CreateRawPdf($entity->invitations->first()))->handle(),
                'file_name' => $entity->getFileName(),
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

        return "{$date}_{$entity}{$number}_{$document->name}";
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

        $file_name = date('Y-m-d-h-i-s') . '_' . str_replace(' ', '_', trans("texts.{$entity_translation_string}")) . '.zip';

        $result = $publisher->publish(
            contents: $this->archive,
            storage_path: $this->company->file_path() . "downloads/{$file_name}",
            download_name: $file_name,
            expires_at: now()->addHour(),
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
