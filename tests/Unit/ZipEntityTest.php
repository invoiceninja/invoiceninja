<?php

namespace Tests\Unit;

use App\Events\Socket\DownloadAvailable;
use App\Export\CSV\BaseExport;
use App\Jobs\Entity\ZipEntity;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Util\UnlinkFile;
use App\Mail\DownloadCredits;
use App\Mail\DownloadDocuments;
use App\Mail\DownloadInvoices;
use App\Mail\DownloadPurchaseOrders;
use App\Mail\DownloadQuotes;
use App\Models\Company;
use App\Models\Credit;
use App\Models\CreditInvitation;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\User;
use App\Services\Download\ArchiveWriter;
use App\Services\Download\TemporaryDownloadPublisher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use Mockery;
use PhpZip\ZipFile;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\MockAccountData;
use Tests\TestCase;

class ZipEntityTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    public function testPreviouslyQueuedJobsDefaultToPdfModeWhenRestored(): void
    {
        $job = (new \ReflectionClass(ZipEntity::class))->newInstanceWithoutConstructor();
        $job->__unserialize([]);

        $this->assertFalse($job->xml_only);
    }

    public function testInvoiceArchiveUsesProtectedDownloadPublisher(): void
    {
        config([
            'filesystems.default' => 'public',
            'filesystems.protected_download_disk' => 'protected-downloads',
            'filesystems.protected_download_allow_unsigned' => false,
        ]);

        Storage::fake('public');
        Storage::fake('protected-downloads');
        Cache::flush();
        URL::forceRootUrl('https://example.test');

        $this->makeTestData();

        Queue::fake([NinjaMailerJob::class, UnlinkFile::class]);
        Event::fake([DownloadAvailable::class]);

        (new ZipEntity(
            [$this->invoice->id],
            $this->company,
            $this->user,
            Invoice::class,
        ))->handle(new ArchiveWriter(), new TemporaryDownloadPublisher());

        $directory = $this->company->file_path() . 'downloads';
        $files = Storage::disk('protected-downloads')->allFiles($directory);

        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.zip', $files[0]);
        Storage::disk('public')->assertMissing($files[0]);

        $archive = (new ZipFile())->openFromString(Storage::disk('protected-downloads')->get($files[0]));

        try {
            $this->assertContains($this->invoice->getFileName(), $archive->getListFiles());
        } finally {
            $archive->close();
        }

        Queue::assertPushed(NinjaMailerJob::class, 1);
        $mailer_job = Queue::pushed(NinjaMailerJob::class)->first();

        $this->assertInstanceOf(NinjaMailerJob::class, $mailer_job);
        $mailable = $mailer_job->nmo?->mailable;
        $this->assertInstanceOf(DownloadInvoices::class, $mailable);

        $download_url = $mailable->file_path;
        $hash = basename((string) parse_url($download_url, PHP_URL_PATH));
        $record = Cache::get($hash);

        $this->assertTrue(URL::hasValidSignature(Request::create($download_url), absolute: false));
        $this->assertIsArray($record);
        $this->assertSame([
            'disk' => 'protected-downloads',
            'path' => $files[0],
            'download_name' => basename($files[0]),
            'expires_at' => $record['expires_at'],
        ], $record);
        $this->assertGreaterThan(now()->timestamp, $record['expires_at']);
        Queue::assertPushed(UnlinkFile::class, 1);
        Event::assertDispatched(
            DownloadAvailable::class,
            fn (DownloadAvailable $event): bool => $event->url === $download_url
                && $event->user->is($this->user),
        );
        Event::assertDispatchedTimes(DownloadAvailable::class, 1);
    }

    public function testQuoteCreditAndPurchaseOrderArchivesUseMatchingMailables(): void
    {
        config([
            'filesystems.default' => 'public',
            'filesystems.protected_download_disk' => 'protected-downloads',
            'filesystems.protected_download_allow_unsigned' => false,
        ]);

        Storage::fake('public');
        Storage::fake('protected-downloads');
        Cache::flush();
        URL::forceRootUrl('https://example.test');

        $this->makeTestData();

        CreditInvitation::firstOrCreate([
            'client_contact_id' => $this->contact->id,
            'credit_id' => $this->credit->id,
        ], [
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'key' => \Illuminate\Support\Str::random(40),
        ]);

        $settings = $this->company->settings;
        $settings->enable_e_invoice = true;
        $this->company->settings = $settings;
        $this->company->save();

        $client_settings = $this->client->settings ?: new \stdClass();
        $client_settings->enable_e_invoice = true;
        $this->client->settings = $client_settings;
        $this->client->save();

        Queue::fake([NinjaMailerJob::class, UnlinkFile::class]);
        Event::fake([DownloadAvailable::class]);

        $entities = [
            [$this->quote, DownloadQuotes::class, [$this->quote->getFileName(), $this->quote->getFileName('xml')]],
            [$this->credit, DownloadCredits::class, [$this->credit->getFileName()]],
            [$this->purchase_order, DownloadPurchaseOrders::class, [$this->purchase_order->getFileName(), $this->purchase_order->getFileName('xml')]],
        ];

        foreach ($entities as [$entity, $mailable_class, $expected_files]) {
            $directory = $this->company->file_path() . 'downloads';
            $existing_files = Storage::disk('protected-downloads')->allFiles($directory);

            (new ZipEntity(
                [$entity->id],
                $this->company,
                $this->user,
                $entity::class,
            ))->handle(new ArchiveWriter(), new TemporaryDownloadPublisher());

            $new_files = array_values(array_diff(
                Storage::disk('protected-downloads')->allFiles($directory),
                $existing_files,
            ));

            $this->assertCount(1, $new_files, $entity::class);

            $archive = (new ZipFile())->openFromString(Storage::disk('protected-downloads')->get($new_files[0]));

            try {
                $this->assertEqualsCanonicalizing($expected_files, $archive->getListFiles(), $entity::class);
            } finally {
                $archive->close();
            }

            $mailer_job = Queue::pushed(NinjaMailerJob::class)->last();

            $this->assertInstanceOf(NinjaMailerJob::class, $mailer_job);
            $this->assertInstanceOf($mailable_class, $mailer_job->nmo?->mailable);
        }

        Queue::assertPushed(NinjaMailerJob::class, 3);
        Queue::assertPushed(UnlinkFile::class, 3);
        Event::assertDispatchedTimes(DownloadAvailable::class, 3);
    }

    public function testDocumentArchiveUsesRawContentsAndDocumentMailable(): void
    {
        config([
            'filesystems.default' => 'public',
            'filesystems.protected_download_disk' => 'protected-downloads',
            'filesystems.protected_download_allow_unsigned' => false,
        ]);

        Storage::fake('public');
        Storage::fake('protected-downloads');
        Storage::fake('source-documents');
        Cache::flush();
        URL::forceRootUrl('https://example.test');

        $this->makeTestData();

        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'supporting-document.txt',
            'type' => 'txt',
            'disk' => 'source-documents',
            'url' => 'documents/supporting-document.txt',
        ]);
        $this->client->documents()->save($document);
        Storage::disk('source-documents')->put($document->url, 'raw document contents');

        Queue::fake([NinjaMailerJob::class, UnlinkFile::class]);
        Event::fake([DownloadAvailable::class]);

        (new ZipEntity(
            [$document->id],
            $this->company,
            $this->user,
            Document::class,
        ))->handle(new ArchiveWriter(), new TemporaryDownloadPublisher());

        $files = Storage::disk('protected-downloads')->allFiles($this->company->file_path() . 'downloads');

        $this->assertCount(1, $files);

        $archive = (new ZipFile())->openFromString(Storage::disk('protected-downloads')->get($files[0]));

        try {
            $entry_name = $archive->getListFiles()[0];

            $this->assertStringEndsWith('_supporting-document.txt', $entry_name);
            $this->assertSame('raw document contents', $archive->getEntryContents($entry_name));
        } finally {
            $archive->close();
        }

        $mailer_job = Queue::pushed(NinjaMailerJob::class)->first();

        $this->assertInstanceOf(NinjaMailerJob::class, $mailer_job);
        $this->assertInstanceOf(DownloadDocuments::class, $mailer_job->nmo?->mailable);
        Queue::assertPushed(UnlinkFile::class, 1);
        Event::assertDispatchedTimes(DownloadAvailable::class, 1);
    }

    public function testUnsupportedEntityClassFailsExplicitly(): void
    {
        $company = new Company();
        $company->db = config('database.default');
        $company->settings = (object) [];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported entity class');

        (new ZipEntity([], $company, new User(), Company::class))->handle(new ArchiveWriter(), new TemporaryDownloadPublisher());
    }

    #[DataProvider('recoverableDocumentTypes')]
    public function testMissingGeneratedDocumentIsRestoredOnItsOriginalDisk(string $extension, bool $throw): void
    {
        $this->prepareDocumentRecovery($throw);
        $document = $this->makeMissingDocument($this->invoice->getFileName($extension));
        // XML can have been classified as text/plain when it was uploaded.
        $document->type = $extension === 'xml' ? 'txt' : 'pdf';
        $document->save();

        $archive = $this->exportDocuments([$document->id]);
        $contents = $document->fresh()->getFile();

        $this->assertIsString($contents);
        $this->assertNotSame('', $contents);
        $this->assertSame(strlen($contents), (int) $document->fresh()->size);
        $this->assertSame($contents, $archive->getEntryContents($archive->getListFiles()[0]));
        $this->assertNotContains('export_errors.txt', $archive->getListFiles());

        if ($extension === 'xml') {
            $xml = new \DOMDocument();
            $this->assertTrue($xml->loadXML($contents));
            $this->assertStringContainsString($this->invoice->number, $contents);
        } else {
            $this->assertStringStartsWith('%PDF-', $contents);
        }

        $archive->close();
    }

    public function testMissingEmbeddedPdfIsRestoredFromStoredSourceData(): void
    {
        $this->prepareDocumentRecovery();
        $pdf = "%PDF-1.4\nOriginal embedded PDF\n%%EOF";
        $this->invoice->e_invoice = ['Invoice' => [
            'InvoiceTypeCode' => ['value' => 389],
            'AdditionalDocumentReference' => [[
                'Attachment' => ['EmbeddedDocumentBinaryObject' => [
                    'filename' => 'original.pdf',
                    'mimeCode' => 'application/pdf',
                    'value' => base64_encode($pdf),
                ]],
            ]],
        ]];
        $this->invoice->save();
        $document = $this->makeMissingDocument('original.pdf');

        $archive = $this->exportDocuments([$document->id]);

        $this->assertSame($pdf, $document->fresh()->getFile());
        $this->assertSame($pdf, $archive->getEntryContents($archive->getListFiles()[0]));
        $archive->close();
    }

    public function testSelfBilledXmlRecoveryPreservesReceivedTypeAndSourceNumber(): void
    {
        $this->prepareDocumentRecovery();
        $this->invoice->e_invoice = ['Invoice' => [
            'ID' => ['value' => 'BUYER-ISSUED-123'],
            'InvoiceTypeCode' => ['value' => 389],
            'DocumentCurrencyCode' => ['value' => 'USD'],
            'IssueDate' => '2026-11-01',
            'InvoiceLine' => [[
                'ID' => ['value' => '1'],
                'InvoicedQuantity' => ['amount' => '-2', 'unitCode' => 'C62'],
            ]],
        ]];
        $this->invoice->save();
        $document = $this->makeMissingDocument('received-original.xml');

        $archive = $this->exportDocuments([$document->id]);
        $xml = new \DOMDocument();
        $this->assertTrue($xml->loadXML($document->fresh()->getFile()));
        $xpath = new \DOMXPath($xml);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $this->assertSame('389', $xpath->evaluate('string(/*/cbc:InvoiceTypeCode)'));
        $this->assertSame('BUYER-ISSUED-123', $xpath->evaluate('string(/*/cbc:ID)'));
        $this->assertSame('-2', $xpath->evaluate('string(//cbc:InvoicedQuantity)'));
        $archive->close();
    }

    public function testUnrecoverableAttachmentDoesNotAbortOtherDocuments(): void
    {
        $this->prepareDocumentRecovery();
        $missing = $this->makeMissingDocument('unavailable-upload.pdf');
        $readable = $this->makeMissingDocument('readable.txt');
        Storage::disk($readable->disk)->put($readable->url, 'readable attachment');

        $archive = $this->exportDocuments([$missing->id, $readable->id]);

        $this->assertCount(2, $archive->getListFiles());
        $this->assertStringContainsString('unavailable-upload.pdf', $archive->getEntryContents('export_errors.txt'));
        $entry = collect($archive->getListFiles())->first(fn ($name) => str_ends_with($name, '_readable.txt'));
        $this->assertSame('readable attachment', $archive->getEntryContents($entry));
        Storage::disk($missing->disk)->assertMissing($missing->url);
        $archive->close();
    }

    public function testExistingUnreadableFileIsNotOverwritten(): void
    {
        $this->prepareDocumentRecovery();
        $document = $this->makeMissingDocument($this->invoice->getFileName('xml'));
        Storage::disk($document->disk)->put($document->url, 'existing original file');
        $unreadable = Mockery::mock(Document::class)->makePartial();
        $unreadable->setRawAttributes($document->getAttributes());
        $unreadable->shouldReceive('getFile')->once()->andThrow(new RuntimeException('Temporary read failure'));
        $job = new ZipEntity([$document->id], $this->company, $this->user, Document::class);

        try {
            (new \ReflectionMethod(ZipEntity::class, 'readDocument'))->invoke($job, $unreadable);
            $this->fail('Existing unreadable file should not be replaced.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('exists but could not be read', $exception->getMessage());
        }

        $this->assertSame('existing original file', $document->getFile());
    }

    public function testRecoveryWriteFailureDoesNotAbortOtherDocuments(): void
    {
        $this->prepareDocumentRecovery();
        $missing = $this->makeMissingDocument($this->invoice->getFileName('xml'));
        $readable = $this->makeMissingDocument('readable.txt');
        Storage::disk($readable->disk)->put($readable->url, 'readable attachment');
        $disk = Mockery::mock(Storage::disk($missing->disk));
        $disk->shouldReceive('put')->with($missing->url, Mockery::type('string'))->once()->andReturn(false);
        Storage::set($missing->disk, $disk);

        $archive = $this->exportDocuments([$missing->id, $readable->id]);

        $this->assertCount(2, $archive->getListFiles());
        $this->assertStringContainsString($missing->name, $archive->getEntryContents('export_errors.txt'));
        $entry = collect($archive->getListFiles())->first(fn ($name) => str_ends_with($name, '_readable.txt'));
        $this->assertSame('readable attachment', $archive->getEntryContents($entry));
        $this->assertSame($missing->size, $missing->fresh()->size);
        $archive->close();
    }

    public static function recoverableDocumentTypes(): array
    {
        return [
            'XML returning null' => ['xml', false],
            'XML throwing a read error' => ['xml', true],
            'generated PDF' => ['pdf', false],
        ];
    }

    private function prepareDocumentRecovery(bool $throw = false): void
    {
        config([
            'filesystems.default' => 'public',
            'filesystems.protected_download_disk' => 'protected-downloads',
            'ninja.pdf_generator' => 'simulator',
        ]);
        Storage::fake('public');
        Storage::fake('protected-downloads');
        Storage::fake('source-documents', ['throw' => $throw]);
        $this->makeTestData();
        Queue::fake([NinjaMailerJob::class, UnlinkFile::class]);
        Event::fake([DownloadAvailable::class]);
    }

    private function makeMissingDocument(string $name): Document
    {
        $document = Document::factory()->make([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => $name,
            'type' => pathinfo($name, PATHINFO_EXTENSION),
            'disk' => 'source-documents',
            'url' => 'documents/' . $name,
        ]);
        $this->invoice->documents()->save($document);

        return $document;
    }

    private function exportDocuments(array $document_ids): ZipFile
    {
        (new ZipEntity($document_ids, $this->company, $this->user, Document::class))
            ->handle(new ArchiveWriter(), new TemporaryDownloadPublisher());
        $files = Storage::disk('protected-downloads')->allFiles($this->company->file_path() . 'downloads');
        $this->assertCount(1, $files);

        return (new ZipFile())->openFromString(Storage::disk('protected-downloads')->get($files[0]));
    }

    public function testInvoiceArchiveFailureDoesNotPublishDownload(): void
    {
        config([
            'filesystems.default' => 'public',
            'filesystems.protected_download_disk' => 'protected-downloads',
        ]);

        Storage::fake('public');
        Storage::fake('protected-downloads');
        Cache::flush();

        $this->makeTestData();

        Queue::fake([NinjaMailerJob::class, UnlinkFile::class]);
        Event::fake([DownloadAvailable::class]);

        $archive_writer = Mockery::mock(ArchiveWriter::class);
        $archive_writer->shouldReceive('write')
            ->once()
            ->andThrow(new RuntimeException('Unable to create protected download archive.', 500));

        $job = new ZipEntity(
            [$this->invoice->id],
            $this->company,
            $this->user,
            Invoice::class,
        );

        try {
            $job->handle($archive_writer, new TemporaryDownloadPublisher());

            $this->fail('Expected archive creation to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unable to create protected download archive.', $exception->getMessage());
            $this->assertSame(500, $exception->getCode());
        }

        $this->assertSame([], Storage::disk('protected-downloads')->allFiles());
        Queue::assertNotPushed(NinjaMailerJob::class);
        Queue::assertNotPushed(UnlinkFile::class);
        Event::assertNotDispatched(DownloadAvailable::class);
    }

    #[DataProvider('pdfEntityClasses')]
    public function testBaseExportQueuesPdfEntitiesThroughZipEntity(string $entity_class): void
    {
        Bus::fake([ZipEntity::class]);

        $user = new User();
        $company = Mockery::mock(Company::class)->makePartial();
        $company->shouldReceive('owner')->once()->andReturn($user);

        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('getModel')->once()->andReturn(new $entity_class());
        $query->shouldReceive('count')->once()->andReturn(2);
        $query->shouldReceive('pluck')->once()->with('id')->andReturn(collect(['invoice-1', 'invoice-2']));

        $export = new BaseExport();
        $export->company = $company;
        $export->input = [];
        $export->queuePdfs($query);

        Bus::assertDispatched(
            ZipEntity::class,
            fn (ZipEntity $job): bool => $job->entity_ids->all() === ['invoice-1', 'invoice-2']
                && $job->company === $company
                && $job->user === $user
                && $job->entity_class === $entity_class,
        );
    }

    public function testBaseExportQueuesDocumentsThroughZipEntity(): void
    {
        Bus::fake([ZipEntity::class]);

        $user = new User();
        $company = Mockery::mock(Company::class)->makePartial();
        $company->shouldReceive('owner')->once()->andReturn($user);

        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('getModel')->once()->andReturn(new Document());
        $query->shouldReceive('pluck')->once()->with('id')->andReturn(collect(['document-1', 'document-2']));

        $export = new BaseExport();
        $export->company = $company;
        $export->input = [];
        $export->queueDocuments($query);

        Bus::assertDispatched(
            ZipEntity::class,
            fn (ZipEntity $job): bool => collect($job->entity_ids)->all() === ['document-1', 'document-2']
                && $job->company === $company
                && $job->user === $user
                && $job->entity_class === Document::class,
        );
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function pdfEntityClasses(): array
    {
        return [
            'invoice' => [Invoice::class],
            'quote' => [Quote::class],
            'credit' => [Credit::class],
            'purchase order' => [PurchaseOrder::class],
        ];
    }
}
