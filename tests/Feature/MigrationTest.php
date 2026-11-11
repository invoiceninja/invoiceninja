<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature;

use App\Http\Middleware\PasswordProtection;
use App\Jobs\Company\CompanyImport;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\FranceReportingEventType;
use App\Services\EDocument\Standards\France\FranceReportingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *
 *  App\Http\Controllers\MigrationController
 */
class MigrationTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ninja.environment' => 'hosted']);
        Session::start();
        Model::reguard();

        $this->makeTestData();
        $this->withoutMiddleware(PasswordProtection::class);
    }

    public function testCompanyExists()
    {
        $co = Company::find($this->company->id);

        // $this->assertNull($this->company);
        $this->assertNotNull($co);
    }

    public function testThatCompanyDeletesCompletely()
    {
        $company_id = $this->company->id;

        $this->company->delete();
        $this->company->fresh();

        $co = Company::find($company_id);

        // $this->assertNull($this->company);
        $this->assertNull($co);
    }

    public function testCompanyChildDeletes()
    {
        $this->makeTestData();

        $this->assertNotNull($this->company);

        $co = Client::whereCompanyId($this->company->id)->get();
        $inv = Invoice::whereCompanyId($this->company->id)->get();

        $this->assertEquals($co->count(), 1);
        $this->assertEquals($inv->count(), 1);
    }

    public function testMigrationPurgePreservingSettingsIgnoresAPendingFranceCallback(): void
    {
        $this->removeFranceReportingSources();
        $this->pendingFranceCallback();

        $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/companies/purge_save_settings/' . $this->company->hashed_id)
            ->assertOk();

        $this->assertFalse(Client::withTrashed()->whereKey($this->client->id)->exists());
    }

    public function testCompanyImportReplacementIgnoresAPendingFranceCallback(): void
    {
        $this->removeFranceReportingSources();
        $this->pendingFranceCallback();
        $import = new CompanyImport($this->company, $this->user, 'unused-location', []);
        $method = new \ReflectionMethod(CompanyImport::class, 'purgeCompanyData');

        $method->invoke($import);

        $this->assertFalse(Client::withTrashed()->whereKey($this->client->id)->exists());
    }

    public function testCompanyImportIgnoresAPendingFranceCallbackRegardlessOfEnvironmentOrSetting(): void
    {
        $this->removeFranceReportingSources();
        $this->pendingFranceCallback();
        $settings = clone $this->company->settings;
        $settings->france_reporting_enabled = false;
        $this->company->settings = $settings;
        $this->company->saveQuietly();
        config(['ninja.environment' => 'selfhost']);
        $import = new CompanyImport($this->company, $this->user, 'unused-location', []);
        $method = new \ReflectionMethod(CompanyImport::class, 'purgeCompanyData');

        $method->invoke($import);

        $this->assertFalse(Client::withTrashed()->whereKey($this->client->id)->exists());
    }

    public function testMigrationPurgeIgnoresAFranceDocumentAwaitingReconciliation(): void
    {
        $this->enableFranceReportingSource();

        $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/companies/purge_save_settings/' . $this->company->hashed_id)
            ->assertOk();

        $this->assertFalse(Client::withTrashed()->whereKey($this->client->id)->exists());
    }

    public function testMigrationPurgePreservesSettingsAndRemovesCompanyData(): void
    {
        $settings = clone $this->company->settings;
        $settings->invoice_number_counter = 27;
        $settings->client_number_counter = 13;
        $this->company->settings = $settings;
        $this->company->saveQuietly();

        $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/companies/purge_save_settings/' . $this->company->hashed_id)
            ->assertOk()
            ->assertJson(['message' => 'Settings preserved']);

        $company = $this->company->fresh();
        $this->assertNotNull($company);
        $this->assertFalse(Client::withTrashed()->whereKey($this->client->id)->exists());
        $this->assertSame(1, $company->settings->invoice_number_counter);
        $this->assertSame(1, $company->settings->client_number_counter);
    }

    public function testMigrationPurgeSkipsFranceChecksWhenReportingIsDisabled(): void
    {
        $this->pendingFranceCallback();
        $settings = clone $this->company->settings;
        $settings->france_reporting_enabled = false;
        $this->company->settings = $settings;
        $this->company->saveQuietly();

        $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/companies/purge_save_settings/' . $this->company->hashed_id)
            ->assertOk();

        $this->assertFalse(Client::withTrashed()->whereKey($this->client->id)->exists());
    }

    public function testCompanyImportIgnoresAFranceDocumentAwaitingReconciliation(): void
    {
        $this->enableFranceReportingSource();
        $import = new CompanyImport($this->company, $this->user, 'unused-location', []);
        $method = new \ReflectionMethod(CompanyImport::class, 'purgeCompanyData');

        $method->invoke($import);

        $this->assertFalse(Client::withTrashed()->whereKey($this->client->id)->exists());
    }

    private function pendingFranceCallback(): TransactionEvent
    {
        $settings = clone $this->company->settings;
        $settings->france_reporting_enabled = true;
        $this->company->settings = $settings;
        $this->company->saveQuietly();

        return TransactionEvent::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'invoice_id' => 0,
            'payment_id' => 0,
            'credit_id' => 0,
            'event_id' => FranceReportingEventType::SubmissionCallback->value,
            'timestamp' => now()->timestamp,
            'period' => now()->toDateString(),
            'payment_status' => FranceReportingStatus::Pending->value,
            'reporting_data' => null,
            'payment_request' => [
                'role' => 'submission_callback',
                'guid' => 'pending-before-submission-guid',
            ],
        ]);
    }

    private function enableFranceReportingSource(): void
    {
        $settings = clone $this->company->settings;
        $settings->france_reporting_enabled = true;
        $this->company->settings = $settings;
        $this->company->saveQuietly();
        $this->invoice->status_id = Invoice::STATUS_SENT;
        $this->invoice->is_deleted = false;
        $this->invoice->saveQuietly();
    }

    private function removeFranceReportingSources(): void
    {
        Invoice::withTrashed()
            ->where('company_id', $this->company->id)
            ->update(['is_deleted' => true]);
        Credit::withTrashed()
            ->where('company_id', $this->company->id)
            ->update(['is_deleted' => true]);
    }

    /** @return array<string, string> */
    private function apiHeaders(): array
    {
        return [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];
    }
}
