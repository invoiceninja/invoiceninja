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
