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

use App\Exceptions\ImportCompanyFailed;
use App\Http\Controllers\MigrationController;
use App\Jobs\Company\CompanyImport;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\FranceReportingEventType;
use App\Services\EDocument\Standards\France\FranceReportingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
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

        Session::start();
        Model::reguard();

        $this->makeTestData();
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

    public function testMigrationPurgePreservingSettingsIsBlockedByAPendingFranceCallback(): void
    {
        $this->pendingFranceCallback();

        $this->expectException(ValidationException::class);

        app(MigrationController::class)->purgeCompanySaveSettings(
            Request::create('/api/v1/companies/purge_save_settings', 'POST'),
            $this->company,
        );
    }

    public function testCompanyImportReplacementIsBlockedByAPendingFranceCallback(): void
    {
        $this->pendingFranceCallback();
        $import = new CompanyImport($this->company, $this->user, 'unused-location', []);
        $method = new \ReflectionMethod(CompanyImport::class, 'purgeCompanyData');

        $this->expectException(ImportCompanyFailed::class);

        $method->invoke($import);
    }

    public function testMigrationPurgeIsBlockedWhileAFranceDocumentAwaitsReconciliation(): void
    {
        $this->enableFranceReportingSource();

        $this->expectException(ValidationException::class);

        app(MigrationController::class)->purgeCompanySaveSettings(
            Request::create('/api/v1/companies/purge_save_settings', 'POST'),
            $this->company,
        );
    }

    public function testCompanyImportIsBlockedWhileAFranceDocumentAwaitsReconciliation(): void
    {
        $this->enableFranceReportingSource();
        $import = new CompanyImport($this->company, $this->user, 'unused-location', []);
        $method = new \ReflectionMethod(CompanyImport::class, 'purgeCompanyData');

        $this->expectException(ImportCompanyFailed::class);

        $method->invoke($import);
    }

    private function pendingFranceCallback(): TransactionEvent
    {
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
}
