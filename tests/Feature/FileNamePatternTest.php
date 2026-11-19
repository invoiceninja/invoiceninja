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

use App\Models\Invoice;
use App\Models\Language;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class FileNamePatternTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

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
