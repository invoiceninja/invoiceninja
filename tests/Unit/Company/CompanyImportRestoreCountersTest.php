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

namespace Tests\Unit\Company;

use App\Jobs\Company\CompanyImport;
use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class CompanyImportRestoreCountersTest extends TestCase
{
    private string $minimal_backup_path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->minimal_backup_path = base_path('tests/Fixtures/Import/minimal_backup.json');
    }

    public function testRestoreCountersFromBackupAppliesBackupValuesAfterDataImport(): void
    {
        if (! file_exists($this->minimal_backup_path)) {
            $this->markTestSkipped('minimal_backup.json fixture is required.');
        }

        $account = Account::factory()->create();
        $company = Company::factory()->create(['account_id' => $account->id]);
        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => 'company-import-restore-counters-' . uniqid('', true) . '@gmail.com',
        ]);

        $settings = $company->settings;
        $settings->invoice_number_counter = 1;
        $settings->quote_number_counter = 1;
        $settings->task_number_counter = 1;
        $company->settings = $settings;
        $company->save();

        $import = new CompanyImport($company, $user, 'unused-location', []);

        $file_path = new ReflectionProperty(CompanyImport::class, 'file_path');
        $file_path->setAccessible(true);
        $file_path->setValue($import, $this->minimal_backup_path);

        $company_property = new ReflectionProperty(CompanyImport::class, 'company');
        $company_property->setAccessible(true);
        $company_property->setValue($import, $company->fresh());

        $method = new ReflectionMethod(CompanyImport::class, 'restoreCountersFromBackup');
        $method->setAccessible(true);
        $method->invoke($import);

        $company->refresh();

        $this->assertSame(3, $company->settings->invoice_number_counter);
        $this->assertSame(3, $company->settings->quote_number_counter);
        $this->assertSame(4, $company->settings->task_number_counter);
        $this->assertSame(2, $company->settings->client_number_counter);
    }

    public function testImportSettingsPreservesExistingCounters(): void
    {
        if (! file_exists($this->minimal_backup_path)) {
            $this->markTestSkipped('minimal_backup.json fixture is required.');
        }

        $account = Account::factory()->create();
        $company = Company::factory()->create(['account_id' => $account->id]);
        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => 'company-import-preserve-counters-' . uniqid('', true) . '@gmail.com',
        ]);

        $settings = $company->settings;
        $settings->invoice_number_counter = 99;
        $settings->quote_number_counter = 88;
        $company->settings = $settings;
        $company->save();

        $import = new CompanyImport($company, $user, 'unused-location', []);

        $file_path = new ReflectionProperty(CompanyImport::class, 'file_path');
        $file_path->setAccessible(true);
        $file_path->setValue($import, $this->minimal_backup_path);

        $company_property = new ReflectionProperty(CompanyImport::class, 'company');
        $company_property->setAccessible(true);
        $company_property->setValue($import, $company->fresh());

        $method = new ReflectionMethod(CompanyImport::class, 'importSettings');
        $method->setAccessible(true);
        $method->invoke($import);

        $company->refresh();

        $this->assertSame(99, $company->settings->invoice_number_counter);
        $this->assertSame(88, $company->settings->quote_number_counter);
    }
}
