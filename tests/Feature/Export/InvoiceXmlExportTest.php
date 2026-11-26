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

namespace Tests\Feature\Export;

use App\Events\Socket\DownloadAvailable;
use App\Export\CSV\InvoiceExport;
use App\Http\Requests\Report\GenericReportRequest;
use App\Jobs\Entity\ZipEntity;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Report\PreviewReport;
use App\Jobs\Util\UnlinkFile;
use App\Mail\DownloadInvoices;
use App\Models\Client;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Download\ArchiveWriter;
use App\Services\Download\TemporaryDownloadPublisher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Mockery;
use PhpZip\ZipFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

class InvoiceXmlExportTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filesystems.default' => 'public',
            'filesystems.protected_download_disk' => 'protected-downloads',
            'filesystems.protected_download_allow_unsigned' => false,
            'queue.default' => 'sync',
        ]);

        Storage::fake('public');
        Storage::fake('protected-downloads');
        Storage::fake('source-documents');
        URL::forceRootUrl('https://example.test');

        $this->makeTestData();

        Queue::fake([NinjaMailerJob::class, UnlinkFile::class]);
        Event::fake([DownloadAvailable::class]);
    }

    #[DataProvider('xmlFormats')]
    public function testXmlArchiveUsesStoredXmlWithGuidAndGeneratesLocallyWithoutGuid(string $format): void
    {
        $settings = $this->client->settings;
        $settings->e_invoice_type = $format;
        $settings->enable_e_invoice = false;
        $this->client->settings = $settings;
        $this->client->classification = 'individual';
        $this->client->save();

        $this->invoice->backup->guid = '';
        $this->invoice->save();
        $this->attachXml($this->invoice, '<Invoice>stale attachment</Invoice>');

        $stored_invoice = $this->createInvoice(['number' => 'STORECOVE-XML']);
        $stored_invoice->backup->guid = 'storecove-guid';
        $stored_invoice->save();
        $stored_xml = '<?xml version="1.0"?><Invoice><ID>STORECOVE-XML</ID></Invoice>';
        $this->attachXml($stored_invoice, $stored_xml);
        $this->assertFalse($stored_invoice->invitations()->exists());

        (new ZipEntity(
            [$stored_invoice->id, $this->invoice->id],
            $this->company,
            $this->user,
            Invoice::class,
            xml_only: true,
        ))->handle(new ArchiveWriter(), new TemporaryDownloadPublisher());

        $files = Storage::disk('protected-downloads')->allFiles($this->company->file_path() . 'downloads');
        $this->assertCount(1, $files);

        $archive = (new ZipFile())->openFromString(Storage::disk('protected-downloads')->get($files[0]));

        try {
            $this->assertEqualsCanonicalizing([
                $stored_invoice->getFileName('xml'),
                $this->invoice->getFileName('xml'),
            ], $archive->getListFiles());
            $this->assertSame($stored_xml, $archive->getEntryContents($stored_invoice->getFileName('xml')));

            $generated_xml = $archive->getEntryContents($this->invoice->getFileName('xml'));
            $document = new \DOMDocument();
            $this->assertTrue($document->loadXML($generated_xml));
            $this->assertStringContainsString($this->invoice->number, $generated_xml);
            $this->assertStringNotContainsString('stale attachment', $generated_xml);
        } finally {
            $archive->close();
        }

        Queue::assertPushed(NinjaMailerJob::class, 1);
        $mailer_job = Queue::pushed(NinjaMailerJob::class)->first();
        $this->assertInstanceOf(DownloadInvoices::class, $mailer_job->nmo->mailable);
        Event::assertDispatchedTimes(DownloadAvailable::class, 1);
        $this->assertSame('', $this->invoice->fresh()->backup->guid);
    }

    public function testReportCanExportPdfAndXmlArchivesTogether(): void
    {
        $this->invoice->date = '2040-01-15';
        $this->invoice->status_id = Invoice::STATUS_SENT;
        $this->invoice->backup->guid = 'storecove-guid';
        $this->invoice->save();
        $stored_xml = '<Invoice><ID>stored invoice</ID></Invoice>';
        $this->attachXml($this->invoice, $stored_xml);

        $input = $this->reportInput();
        $input['pdf_email_attachment'] = true;
        (new InvoiceExport($this->company, $input))->run();

        $files = Storage::disk('protected-downloads')->allFiles($this->company->file_path() . 'downloads');
        $this->assertCount(2, $files);

        $xml_archive_path = collect($files)->first(fn (string $file): bool => str_ends_with($file, '_xml.zip'));
        $this->assertNotNull($xml_archive_path);
        $archive = (new ZipFile())->openFromString(Storage::disk('protected-downloads')->get($xml_archive_path));

        try {
            $this->assertSame([$this->invoice->getFileName('xml')], $archive->getListFiles());
            $this->assertSame($stored_xml, $archive->getEntryContents($this->invoice->getFileName('xml')));
        } finally {
            $archive->close();
        }

        Queue::assertPushed(NinjaMailerJob::class, 2);
    }

    public function testGuidWithoutStoredXmlRecreatesTheAttachmentAndPublishesAnArchive(): void
    {
        $this->invoice->backup->guid = 'storecove-guid';
        $this->invoice->save();

        (new ZipEntity(
            [$this->invoice->id],
            $this->company,
            $this->user,
            Invoice::class,
            xml_only: true,
        ))->handle(new ArchiveWriter(), new TemporaryDownloadPublisher());

        $document = $this->invoice->documents()->where('name', $this->invoice->getFileName('xml'))->firstOrFail();
        $xml = new \DOMDocument();
        $this->assertTrue($xml->loadXML($document->getFile()));
        $this->assertStringContainsString($this->invoice->number, $document->getFile());
        $this->assertCount(1, Storage::disk('protected-downloads')->allFiles());
        Queue::assertPushed(NinjaMailerJob::class, 1);
        Event::assertDispatchedTimes(DownloadAvailable::class, 1);
    }

    public function testXmlAttachmentJobReceivesOnlyInvoicesMatchingReportFilters(): void
    {
        Bus::fake([ZipEntity::class]);

        $first_invoice = $this->createInvoice();
        $second_invoice = $this->createInvoice();
        $this->createInvoice(['date' => '2039-12-31']);
        $this->createInvoice(['status_id' => Invoice::STATUS_PAID]);
        $this->createInvoice(['is_deleted' => true]);
        $other_client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);
        $this->createInvoice(['client_id' => $other_client->id]);

        (new InvoiceExport($this->company, $this->reportInput()))->run();

        Bus::assertDispatchedTimes(ZipEntity::class, 1);
        Bus::assertDispatched(ZipEntity::class, fn (ZipEntity $job): bool => $job->xml_only
            && $job->entity_class === Invoice::class
            && $job->company->is($this->company)
            && $job->user->is($this->user)
            && $job->entity_ids->sort()->values()->all() === collect([$first_invoice->id, $second_invoice->id])->sort()->values()->all());
    }

    public function testFalseOrOmittedXmlFlagDoesNotQueueXmlAttachments(): void
    {
        Bus::fake([ZipEntity::class]);
        $this->createInvoice();
        $input = $this->reportInput();
        $input['xml_email_attachment'] = false;
        (new InvoiceExport($this->company, $input))->run();

        unset($input['xml_email_attachment']);
        (new InvoiceExport($this->company, $input))->run();

        Bus::assertNotDispatched(ZipEntity::class);
    }

    public function testEmptyReportDoesNotQueueXmlAttachments(): void
    {
        Bus::fake([ZipEntity::class]);
        $input = $this->reportInput();
        $input['start_date'] = '2100-01-01';
        $input['end_date'] = '2100-01-02';

        (new InvoiceExport($this->company, $input))->run();

        Bus::assertNotDispatched(ZipEntity::class);
    }

    public function testPreviewDoesNotQueueXmlAttachments(): void
    {
        Bus::fake([ZipEntity::class]);
        $this->createInvoice();

        (new PreviewReport(
            $this->company,
            $this->reportInput(),
            InvoiceExport::class,
            'invoice-xml-preview',
            'invoices.csv',
            $this->user,
        ))->handle();

        $this->assertNotEmpty(Cache::pull('invoice-xml-preview'));
        Bus::assertNotDispatched(ZipEntity::class);
    }

    public function testReportRequestValidatesXmlFlagAsBoolean(): void
    {
        $request = new GenericReportRequest();
        $input = $this->reportInput();

        $this->assertTrue(Validator::make($input, $request->rules())->passes());

        $input['xml_email_attachment'] = 'invalid';
        $this->assertTrue(Validator::make($input, $request->rules())->errors()->has('xml_email_attachment'));
    }

    public function testReportRequestClearsXmlFlagWithoutReportPermission(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = $this->user->id;
        $user->shouldReceive('isAdmin')->andReturn(false);
        $user->shouldReceive('hasPermission')->with('view_reports')->andReturn(false);
        $this->actingAs($user);

        $request = new GenericReportRequest($this->reportInput());
        $request->prepareForValidation();

        $this->assertFalse($request->input('xml_email_attachment'));
    }

    private function createInvoice(array $attributes = []): Invoice
    {
        return Invoice::factory()->create(array_merge([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'date' => '2040-01-15',
            'status_id' => Invoice::STATUS_SENT,
            'is_deleted' => false,
        ], $attributes));
    }

    private function attachXml(Invoice $invoice, string $xml): void
    {
        $document = Document::factory()->make([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => $invoice->getFileName('xml'),
            'type' => 'xml',
            'disk' => 'source-documents',
            'url' => "documents/{$invoice->id}.xml",
        ]);
        $invoice->documents()->save($document);
        Storage::disk('source-documents')->put($document->url, $xml);
    }

    private function reportInput(): array
    {
        return [
            'date_range' => 'custom',
            'start_date' => '2040-01-01',
            'end_date' => '2040-01-31',
            'report_keys' => ['invoice.number'],
            'send_email' => true,
            'include_deleted' => false,
            'client_id' => $this->client->hashed_id,
            'status' => 'sent',
            'user_id' => $this->user->id,
            'xml_email_attachment' => true,
        ];
    }

    public static function xmlFormats(): array
    {
        return [
            'EN16931' => ['EN16931'],
            'Peppol' => ['PEPPOL'],
        ];
    }
}
