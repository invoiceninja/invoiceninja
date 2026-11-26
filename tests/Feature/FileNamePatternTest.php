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

namespace Tests\Feature;

use App\Events\Socket\DownloadAvailable;
use App\Jobs\Entity\ZipEntity;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Util\UnlinkFile;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\Language;
use App\Models\PurchaseOrder;
use App\Services\Download\ArchiveWriter;
use App\Services\Download\TemporaryDownloadPublisher;
use App\Utils\FileNamePattern;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use PhpZip\ZipFile;
use Tests\MockAccountData;
use Tests\TestCase;

class FileNamePatternTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;
    use MakesHash;

    protected function setUp(): void
    {
        parent::setUp();

        Model::reguard();

        $this->makeTestData();

        $this->client->name = 'Acme Ltd';
        $this->client->number = 'C-7';
        $this->client->save();

        $this->invoice->number = '0042';
        $this->invoice->date = '2026-11-19';
        $this->invoice->due_date = '2026-12-19';
        $this->invoice->po_number = 'PO-9';
        $this->invoice->save();

        $settings = $this->company->settings;
        $settings->name = 'Widgets Inc';
        $this->company->settings = $settings;
        $this->company->save();

        // The test group copies every company setting; a real group holds only
        // the ones it overrides.
        $group = $this->client->group_settings;
        $group_settings = $group->settings;

        foreach (['invoice_filename_pattern', 'quote_filename_pattern', 'credit_filename_pattern', 'purchase_order_filename_pattern', 'filename_slug', 'translations', 'name'] as $key) {
            unset($group_settings->{$key});
        }

        $group->settings = $group_settings;
        $group->save();
    }

    private function setCompanySetting(string $key, mixed $value): void
    {
        $settings = $this->company->settings;
        $settings->{$key} = $value;
        $this->company->settings = $settings;
        $this->company->save();
    }

    private function invoice(): Invoice
    {
        return Invoice::find($this->invoice->id);
    }

    public function testNoPatternKeepsTheDefaultName(): void
    {
        $this->assertNull($this->invoice()->customFileName());
    }

    public function testAPatternWithoutTheNumberIsIgnored(): void
    {
        $this->setCompanySetting('invoice_filename_pattern', '$client.name - $entity');

        $this->assertNull($this->invoice()->customFileName());
    }

    public function testADocumentWithoutANumberKeepsTheDefaultName(): void
    {
        $this->setCompanySetting('invoice_filename_pattern', '$number');

        $invoice = $this->invoice();
        $invoice->number = null;

        $this->assertNull($invoice->customFileName());
    }

    public function testVariablesAndSpacesAreKept(): void
    {
        $this->setCompanySetting('invoice_filename_pattern', '$company.name - $entity - $number');

        $this->assertSame('Widgets Inc - Invoice - 0042.pdf', $this->invoice()->customFileName());
        $this->assertSame('Widgets Inc - Invoice - 0042.xml', $this->invoice()->customFileName('xml'));
    }

    public function testEveryVariable(): void
    {
        $this->setCompanySetting('invoice_filename_pattern', '$number $entity $date $due_date $po_number $client.name $client.number');

        $this->assertSame('0042 Invoice 2026-11-19 2026-12-19 PO-9 Acme Ltd C-7.pdf', $this->invoice()->customFileName());
    }

    public function testBracedVariablesAndAnUnderscoreAfterAVariable(): void
    {
        $this->setCompanySetting('invoice_filename_pattern', '$date_$client.name_{$number}');

        $this->assertSame('2026-11-19_Acme Ltd_0042.pdf', $this->invoice()->customFileName());
    }

    public function testContactNamesAreThePrimaryContacts(): void
    {
        $this->setCompanySetting('invoice_filename_pattern', '$contact.last_name $number');

        $contact = $this->invoice()->client->contacts()->first();
        $contact->last_name = 'Müller';
        $contact->save();

        $this->assertSame('Muller 0042.pdf', $this->invoice()->customFileName());
    }

    public function testCharactersAreStrippedLikeTheDefaultName(): void
    {
        $this->setCompanySetting('invoice_filename_pattern', '$client.name / $number');

        $this->client->name = 'O\'Brien & "Sons" 50% <x>|y';
        $this->client->save();

        $this->assertSame('OBrien Sons 50 xy 0042.pdf', $this->invoice()->customFileName());
    }

    public function testAccentsFollowTheClientLanguage(): void
    {
        $this->setCompanySetting('invoice_filename_pattern', '$client.name $number');

        $this->client->name = 'Müller Söhne';
        $settings = $this->client->settings;
        $settings->language_id = (string) Language::where('locale', 'de')->first()->id;
        $this->client->settings = $settings;
        $this->client->save();

        $this->assertSame('Mueller Soehne 0042.pdf', $this->invoice()->customFileName());
    }

    public function testTheClientAndGroupOverrideTheCompany(): void
    {
        $this->setCompanySetting('invoice_filename_pattern', 'company $number');

        $group = $this->client->group_settings;
        $group_settings = $group->settings;
        $group_settings->invoice_filename_pattern = 'group $number';
        $group->settings = $group_settings;
        $group->save();

        $this->assertSame('group 0042.pdf', $this->invoice()->customFileName());

        $settings = $this->client->settings;
        $settings->invoice_filename_pattern = 'client $number';
        $this->client->settings = $settings;
        $this->client->save();

        $this->assertSame('client 0042.pdf', $this->invoice()->customFileName());
    }

    public function testTheCompanyTranslationOfTheType(): void
    {
        $this->setCompanySetting('invoice_filename_pattern', '$entity $number');
        $this->setCompanySetting('translations', (object) ['invoice' => 'Tax Invoice']);

        $this->assertSame('Tax Invoice 0042.pdf', $this->invoice()->customFileName());
    }

    public function testSimplifiedFileNames(): void
    {
        $this->setCompanySetting('invoice_filename_pattern', '$company.name - $entity - $number');
        $this->setCompanySetting('filename_slug', true);

        $this->assertSame('widgets-inc-invoice-0042.pdf', $this->invoice()->customFileName());
    }

    public function testALongValueIsShortenedSoTheNumberStays(): void
    {
        $this->setCompanySetting('invoice_filename_pattern', '$client.name - $number');

        $this->client->name = str_repeat('Acme ', 40);
        $this->client->save();

        $name = $this->invoice()->customFileName();

        $this->assertSame(str_repeat('Acme ', 8) . '- 0042.pdf', $name);
    }

    public function testSeveralLongValuesKeepTheNumber(): void
    {
        $this->setCompanySetting('invoice_filename_pattern', '$client.custom1 $client.custom2 $client.custom3 $client.custom4 $number');
        $this->setLongCustomValues();

        $this->assertSame(
            str_repeat('1', 35) . ' ' . str_repeat('2', 35) . ' ' . str_repeat('3', 35) . ' ' . str_repeat('4', 35) . ' 0042.pdf',
            $this->invoice()->customFileName()
        );
    }

    public function testDocumentsWithLongNamesGetTheirOwnZipEntries(): void
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
        Queue::fake([NinjaMailerJob::class, UnlinkFile::class]);
        Event::fake([DownloadAvailable::class]);

        $this->setCompanySetting('invoice_filename_pattern', '$client.custom1 $client.custom2 $client.custom3 $client.custom4 $number');
        $this->setLongCustomValues();

        $second = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'number' => '0043',
        ]);

        $invitation = new InvoiceInvitation();
        $invitation->company_id = $this->company->id;
        $invitation->user_id = $this->user->id;
        $invitation->client_contact_id = $this->contact->id;
        $invitation->invoice_id = $second->id;
        $invitation->key = Str::random(40);
        $invitation->save();

        (new ZipEntity([$this->invoice->id, $second->id], $this->company, $this->user, Invoice::class))
            ->handle(new ArchiveWriter(), new TemporaryDownloadPublisher());

        $files = Storage::disk('protected-downloads')->allFiles($this->company->file_path() . 'downloads');
        $archive = (new ZipFile())->openFromString(Storage::disk('protected-downloads')->get($files[0]));

        try {
            $first_name = $this->invoice()->customFileName();
            $second_name = Invoice::find($second->id)->customFileName();

            $this->assertNotSame($first_name, $second_name);
            $this->assertContains($first_name, $archive->getListFiles());
            $this->assertContains($second_name, $archive->getListFiles());
        } finally {
            $archive->close();
        }
    }

    public function testANameThatWouldLoseItsNumberKeepsTheDefaultName(): void
    {
        $this->setCompanySetting('invoice_filename_pattern', str_repeat('a', 150) . ' $number');

        $invoice = $this->invoice();

        $this->assertNull($invoice->customFileName());
        $this->assertSame($invoice->getFileName(), $invoice->getDownloadFileName());
    }

    public function testEagerLoadedDocumentsNeedNoQueries(): void
    {
        $this->setCompanySetting('invoice_filename_pattern', '$client.name $contact.last_name $number');

        $contact = $this->client->contacts()->first();
        $contact->last_name = 'Smith';
        $contact->save();

        // Reads the languages into the cache.
        $this->invoice()->customFileName();

        $invoice = Invoice::with(FileNamePattern::relations(Invoice::class))->find($this->invoice->id);

        DB::enableQueryLog();

        $this->assertSame('Acme Ltd Smith 0042.pdf', $invoice->customFileName());
        $this->assertSame('Acme Ltd Smith 0042.xml', $invoice->customFileName('xml'));
        $this->assertSame([], DB::getQueryLog());

        DB::disableQueryLog();
    }

    public function testEagerLoadedPurchaseOrdersNeedNoQueries(): void
    {
        $this->setCompanySetting('purchase_order_filename_pattern', '$vendor.name $contact.first_name $entity $number');

        // A vendor's own language is read through a relation.
        $this->vendor->name = 'Supplies Co';
        $this->vendor->language_id = Language::where('locale', 'de')->first()->id;
        $this->vendor->save();

        $this->purchase_order->number = 'PO1';
        $this->purchase_order->save();

        // Reads the languages into the cache.
        $this->purchase_order->fresh()->customFileName();

        $purchase_order = PurchaseOrder::with(FileNamePattern::relations(PurchaseOrder::class))->find($this->purchase_order->id);

        DB::enableQueryLog();

        $name = $purchase_order->customFileName();
        $this->assertStringStartsWith('Supplies Co ', $name);
        $this->assertStringEndsWith(' PO1.pdf', $name);
        $this->assertSame([], DB::getQueryLog());

        DB::disableQueryLog();
    }

    public function testAPatternIsAtMost150Characters(): void
    {
        $headers = [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-Token' => $this->token,
        ];

        $settings = $this->company->settings;
        $settings->invoice_filename_pattern = str_repeat('a', 143) . '$number';

        $this->withHeaders($headers)->putJson('/api/v1/companies/' . $this->encodePrimaryKey($this->company->id), ['settings' => $settings])
            ->assertStatus(200);

        $settings->invoice_filename_pattern = str_repeat('a', 144) . '$number';

        $this->withHeaders($headers)->putJson('/api/v1/companies/' . $this->encodePrimaryKey($this->company->id), ['settings' => $settings])
            ->assertStatus(422)
            ->assertJsonValidationErrors('settings.invoice_filename_pattern');

        $this->withHeaders($headers)->putJson('/api/v1/clients/' . $this->client->hashed_id, ['settings' => ['invoice_filename_pattern' => str_repeat('a', 144) . '$number']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('settings.invoice_filename_pattern');

        $this->withHeaders($headers)->putJson('/api/v1/group_settings/' . $this->client->group_settings->hashed_id, ['settings' => ['invoice_filename_pattern' => str_repeat('a', 144) . '$number']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('settings.invoice_filename_pattern');
    }

    private function setLongCustomValues(): void
    {
        foreach ([1, 2, 3, 4] as $i) {
            $this->client->{'custom_value' . $i} = str_repeat((string) $i, 60);
        }

        $this->client->save();
    }

    public function testPurchaseOrdersUseTheVendor(): void
    {
        $this->setCompanySetting('purchase_order_filename_pattern', '$vendor.name - $entity - $number');

        $this->vendor->name = 'Supplies Co';
        $this->vendor->save();

        $purchase_order = $this->purchase_order->fresh();
        $purchase_order->number = 'PO1';

        $this->assertSame('Supplies Co - Purchase Order - PO1.pdf', $purchase_order->customFileName());
    }

    public function testTheDownloadIsNamedByThePattern(): void
    {
        $this->setCompanySetting('invoice_filename_pattern', '$company.name - $entity - $number');

        $invitation = $this->invoice()->invitations()->first();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-Token' => $this->token,
        ])->get('/api/v1/invoice/' . $invitation->key . '/download');

        $response->assertStatus(200);
        $this->assertSame('attachment; filename="Widgets Inc - Invoice - 0042.pdf"', $response->headers->get('Content-Disposition'));
    }

    public function testTheDownloadKeepsTheDefaultNameWithoutAPattern(): void
    {
        $invitation = $this->invoice()->invitations()->first();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-Token' => $this->token,
        ])->get('/api/v1/invoice/' . $invitation->key . '/download');

        $response->assertStatus(200);
        $this->assertSame('attachment; filename=Invoice_0042.pdf', $response->headers->get('Content-Disposition'));
    }
}
