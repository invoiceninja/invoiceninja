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

use App\DataMapper\CompanySettings;
use App\Export\CSV\CreditExport;
use App\Export\CSV\ExpenseExport;
use App\Export\CSV\InvoiceExport;
use App\Export\CSV\PaymentExport;
use App\Factory\CompanyUserFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use League\Csv\Reader;
use Tests\TestCase;

class ReportGroupByTest extends TestCase
{
    use MakesHash;

    public $faker;

    public $company;

    public $user;

    public $account;

    public $client;

    public $client2;

    public $token;

    public $cu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();

        config(['queue.default' => 'sync']);

        $this->buildData();

        if (config('ninja.testvars.travis') !== false) {
            $this->markTestSkipped('Skip test no company gateways installed');
        }
    }

    private function buildData(): void
    {
        $this->account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $this->account->num_users = 3;
        $this->account->save();

        $this->user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => 'xyz123',
            'email' => \Illuminate\Support\Str::random(32) . '@example.com',
        ]);

        $settings = CompanySettings::defaults();
        $settings->client_online_payment_notification = false;
        $settings->client_manual_payment_notification = false;

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $this->company->settings = $settings;
        $this->company->save();

        $this->cu = CompanyUserFactory::create($this->user->id, $this->company->id, $this->account->id);
        $this->cu->is_owner = true;
        $this->cu->is_admin = true;
        $this->cu->is_locked = false;
        $this->cu->save();

        $this->token = \Illuminate\Support\Str::random(64);

        $company_token = new CompanyToken();
        $company_token->user_id = $this->user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'test token';
        $company_token->token = $this->token;
        $company_token->is_system = true;
        $company_token->save();

        $this->client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
            'name' => 'Client Alpha',
            'balance' => 100,
            'paid_to_date' => 50,
        ]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);

        $this->client2 = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
            'name' => 'Client Beta',
            'balance' => 200,
            'paid_to_date' => 100,
        ]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client2->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);
    }

    /**
     * Helper to parse grouped CSV output into an associative array keyed by the first column value.
     *
     * @return array<string, array<string, string>>
     */
    private function parseCsvByFirstColumn(string $csv): array
    {
        $reader = Reader::createFromString($csv);
        $reader->setHeaderOffset(0);

        $result = [];
        foreach ($reader->getRecords() as $record) {
            $values = array_values($record);
            $headers = array_keys($record);
            $result[$values[0]] = array_combine($headers, $values);
        }

        return $result;
    }

    /**
     * Helper to extract data rows from grouped JSON output.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function extractJsonDataRows(array $json): array
    {
        return array_values(array_filter($json, fn ($k) => is_int($k), ARRAY_FILTER_USE_KEY));
    }

    /**
     * Helper to find a cell value in a JSON data row by identifier.
     */
    private function getJsonCellValue(array $row, string $identifier): mixed
    {
        $cell = collect($row)->firstWhere('identifier', $identifier);

        return $cell['value'] ?? null;
    }

    /**
     * Write an artifact file to tests/artifacts/.
     */
    private function writeArtifact(string $filename, string $content): void
    {
        $path = base_path('tests/artifacts/' . $filename);
        file_put_contents($path, $content);
    }

    // ---------------------------------------------------------------
    // CSV Output Tests
    // ---------------------------------------------------------------

    public function testCsvGroupByClientSumsAmountsCorrectly(): void
    {
        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 100.0,
            'balance' => 40.0,
            'paid_to_date' => 60.0,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 200.0,
            'balance' => 80.0,
            'paid_to_date' => 120.0,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        Invoice::factory()->create([
            'client_id' => $this->client2->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 500.0,
            'balance' => 500.0,
            'paid_to_date' => 0.0,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $export = new InvoiceExport($this->company, [
            'date_range' => 'all',
            'report_keys' => ['client.name', 'invoice.amount', 'invoice.balance', 'invoice.paid_to_date'],
            'send_email' => false,
            'group_by' => 'client.name',
            'include_deleted' => false,
        ]);

        $csv = $export->groupedRun();
        $this->writeArtifact('group_by_invoice_by_client.csv', $csv);

        $json = $export->groupedReturnJson();
        $this->writeArtifact('group_by_invoice_by_client.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $rows = $this->parseCsvByFirstColumn($csv);

        // Two groups: Client Alpha (2 invoices) and Client Beta (1 invoice)
        $this->assertCount(2, $rows);
        $this->assertArrayHasKey('Client Alpha', $rows);
        $this->assertArrayHasKey('Client Beta', $rows);

        // Client Alpha: 100 + 200 = 300 amount, 40 + 80 = 120 balance, 60 + 120 = 180 paid
        $alpha = $rows['Client Alpha'];
        $this->assertEquals('300.00', $alpha['Invoice Amount']);
        $this->assertEquals('120.00', $alpha['Invoice Balance']);
        $this->assertEquals('180.00', $alpha['Invoice Paid to Date']);
        $this->assertEquals('2', $alpha['Count']);

        // Client Beta: single invoice 500 amount, 500 balance, 0 paid
        $beta = $rows['Client Beta'];
        $this->assertEquals('500.00', $beta['Invoice Amount']);
        $this->assertEquals('500.00', $beta['Invoice Balance']);
        $this->assertEquals('0.00', $beta['Invoice Paid to Date']);
        $this->assertEquals('1', $beta['Count']);
    }

    public function testCsvGroupByStatusProducesCorrectGroups(): void
    {
        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 100.0,
            'balance' => 100.0,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 200.0,
            'balance' => 200.0,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        Invoice::factory()->create([
            'client_id' => $this->client2->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 400.0,
            'balance' => 0.0,
            'status_id' => Invoice::STATUS_PAID,
        ]);

        $export = new InvoiceExport($this->company, [
            'date_range' => 'all',
            'report_keys' => ['invoice.status', 'invoice.amount', 'invoice.balance'],
            'send_email' => false,
            'group_by' => 'invoice.status',
            'include_deleted' => false,
        ]);

        $csv = $export->groupedRun();
        $this->writeArtifact('group_by_invoice_by_status.csv', $csv);

        $rows = $this->parseCsvByFirstColumn($csv);

        // Two status groups: Sent and Paid
        $this->assertCount(2, $rows);
        $this->assertArrayHasKey('Sent', $rows);
        $this->assertArrayHasKey('Paid', $rows);

        $this->assertEquals('300.00', $rows['Sent']['Invoice Amount']);
        $this->assertEquals('300.00', $rows['Sent']['Invoice Balance']);
        $this->assertEquals('2', $rows['Sent']['Count']);

        $this->assertEquals('400.00', $rows['Paid']['Invoice Amount']);
        $this->assertEquals('0.00', $rows['Paid']['Invoice Balance']);
        $this->assertEquals('1', $rows['Paid']['Count']);
    }

    public function testCsvNonSummableColumnsAreBlank(): void
    {
        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 100.0,
            'balance' => 50.0,
            'exchange_rate' => 1.5,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 200.0,
            'balance' => 75.0,
            'exchange_rate' => 1.5,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $export = new InvoiceExport($this->company, [
            'date_range' => 'all',
            'report_keys' => ['client.name', 'invoice.amount', 'invoice.balance', 'invoice.exchange_rate'],
            'send_email' => false,
            'group_by' => 'client.name',
            'include_deleted' => false,
        ]);

        $csv = $export->groupedRun();
        $this->writeArtifact('group_by_invoice_non_summable.csv', $csv);

        $rows = $this->parseCsvByFirstColumn($csv);

        $alpha = $rows['Client Alpha'];

        // Amounts are summed
        $this->assertEquals('300.00', $alpha['Invoice Amount']);
        $this->assertEquals('125.00', $alpha['Invoice Balance']);

        // Exchange rate is non-summable — must be blank
        $this->assertEquals('', $alpha['Invoice Exchange Rate']);

        // Count still works
        $this->assertEquals('2', $alpha['Count']);
    }

    public function testCsvNonNumericColumnPreservedWhenAllRowsAgree(): void
    {
        // Single invoice for Client Alpha, grouped by status. The group has one
        // row, so client.name is uniform across the group and must be preserved.
        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 100.0,
            'balance' => 100.0,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $export = new InvoiceExport($this->company, [
            'date_range' => 'all',
            'report_keys' => ['invoice.status', 'invoice.amount', 'invoice.balance'],
            'send_email' => false,
            'group_by' => 'invoice.status',
            'include_deleted' => false,
        ]);

        $csv = $export->groupedRun();
        $this->writeArtifact('group_by_invoice_non_numeric_preserved.csv', $csv);

        $rows = $this->parseCsvByFirstColumn($csv);

        $sent = $rows['Sent'];

        $this->assertEquals('Sent', $sent['Invoice Status']);
        // client.name is uniform across the group → preserved (was blanked under old logic).
        $this->assertEquals('Client Alpha', $sent['Client Name']);
    }

    public function testCsvNonNumericColumnBlankedWhenRowsDiffer(): void
    {
        // Two clients in the same status group → client.name differs across the
        // group → must be blanked because there is no single correct value.
        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 100.0,
            'balance' => 100.0,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        Invoice::factory()->create([
            'client_id' => $this->client2->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 200.0,
            'balance' => 200.0,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $export = new InvoiceExport($this->company, [
            'date_range' => 'all',
            'report_keys' => ['invoice.status', 'invoice.amount', 'invoice.balance'],
            'send_email' => false,
            'group_by' => 'invoice.status',
            'include_deleted' => false,
        ]);

        $csv = $export->groupedRun();
        $this->writeArtifact('group_by_invoice_non_numeric_differ.csv', $csv);

        $rows = $this->parseCsvByFirstColumn($csv);

        $sent = $rows['Sent'];

        $this->assertEquals('Sent', $sent['Invoice Status']);
        $this->assertEquals('300.00', $sent['Invoice Amount']);
        // Different client names in the group → blank.
        $this->assertEquals('', $sent['Client Name']);
    }

    public function testCsvClientReportGroupByNumberPreservesName(): void
    {
        // The user-reported case: grouping clients by their (unique) number.
        // Each group contains a single client, so name must render — not blank.
        $this->client->number = 'C-0001';
        $this->client->save();

        $this->client2->number = 'C-0002';
        $this->client2->save();

        $export = new \App\Export\CSV\ClientExport($this->company, [
            'date_range' => 'all',
            'report_keys' => ['client.name', 'client.number', 'client.balance'],
            'send_email' => false,
            'group_by' => 'client.number',
            'include_deleted' => false,
        ]);

        $csv = $export->groupedRun();
        $this->writeArtifact('group_by_client_by_number.csv', $csv);

        $rows = $this->parseCsvByFirstColumn($csv);

        // Report column order is name, number, balance — so rows are keyed by name.
        $this->assertArrayHasKey('Client Alpha', $rows);
        $this->assertArrayHasKey('Client Beta', $rows);

        $this->assertEquals('C-0001', $rows['Client Alpha']['Number']);
        $this->assertEquals('100.00', $rows['Client Alpha']['Balance']);

        $this->assertEquals('C-0002', $rows['Client Beta']['Number']);
        $this->assertEquals('200.00', $rows['Client Beta']['Balance']);
    }

    public function testJsonNonNumericColumnPreservedWhenAllRowsAgree(): void
    {
        // Two invoices, same client → client.name is uniform across the status
        // group → preserved in JSON output.
        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 100.0,
            'balance' => 100.0,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 200.0,
            'balance' => 200.0,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $export = new InvoiceExport($this->company, [
            'date_range' => 'all',
            'report_keys' => ['invoice.status', 'client.name', 'invoice.amount'],
            'send_email' => false,
            'group_by' => 'invoice.status',
            'include_deleted' => false,
        ]);

        $result = $export->groupedReturnJson();
        $this->writeArtifact('group_by_invoice_json_name_preserved.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $data_rows = $this->extractJsonDataRows($result);
        $sent_row = collect($data_rows)->first(fn ($row) => $this->getJsonCellValue($row, 'invoice.status') === 'Sent');

        $this->assertNotNull($sent_row);
        $this->assertEquals('Client Alpha', $this->getJsonCellValue($sent_row, 'client.name'));
        $this->assertEquals('300.00', $this->getJsonCellValue($sent_row, 'invoice.amount'));
        $this->assertEquals(2, $this->getJsonCellValue($sent_row, 'group.count'));
    }

    public function testCsvEmptyResultSetProducesHeaderOnly(): void
    {
        $export = new InvoiceExport($this->company, [
            'date_range' => 'custom',
            'start_date' => '1990-01-01',
            'end_date' => '1990-01-02',
            'report_keys' => ['client.name', 'invoice.amount'],
            'send_email' => false,
            'group_by' => 'client.name',
            'include_deleted' => false,
        ]);

        $csv = $export->groupedRun();
        $this->writeArtifact('group_by_invoice_empty_result.csv', $csv);

        $reader = Reader::createFromString($csv);
        $reader->setHeaderOffset(0);
        $records = iterator_to_array($reader->getRecords());

        $this->assertCount(0, $records);

        // Header should still be present with Count column
        $header = $reader->getHeader();
        $this->assertContains('Count', $header);
    }

    public function testCsvExpenseGroupByCategorySums(): void
    {
        $travel = ExpenseCategory::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Travel',
        ]);

        $office = ExpenseCategory::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Office',
        ]);

        Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'category_id' => $travel->id,
            'amount' => 150.0,
        ]);

        Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'category_id' => $travel->id,
            'amount' => 250.0,
        ]);

        Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'category_id' => $office->id,
            'amount' => 75.0,
        ]);

        $export = new ExpenseExport($this->company, [
            'date_range' => 'all',
            'report_keys' => ['expense.category', 'expense.amount'],
            'send_email' => false,
            'group_by' => 'expense.category',
            'include_deleted' => false,
        ]);

        $csv = $export->groupedRun();
        $this->writeArtifact('group_by_expense_by_category.csv', $csv);

        $json = $export->groupedReturnJson();
        $this->writeArtifact('group_by_expense_by_category.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $rows = $this->parseCsvByFirstColumn($csv);

        $this->assertCount(2, $rows);
        $this->assertArrayHasKey('Travel', $rows);
        $this->assertArrayHasKey('Office', $rows);

        $this->assertEquals('400.00', $rows['Travel']['Expense Amount']);
        $this->assertEquals('2', $rows['Travel']['Count']);

        $this->assertEquals('75.00', $rows['Office']['Expense Amount']);
        $this->assertEquals('1', $rows['Office']['Count']);
    }

    // ---------------------------------------------------------------
    // JSON Output Tests
    // ---------------------------------------------------------------

    public function testJsonGroupByClientStructureAndValues(): void
    {
        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 100.0,
            'balance' => 40.0,
            'paid_to_date' => 60.0,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 200.0,
            'balance' => 80.0,
            'paid_to_date' => 120.0,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        Invoice::factory()->create([
            'client_id' => $this->client2->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 500.0,
            'balance' => 500.0,
            'paid_to_date' => 0.0,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $export = new InvoiceExport($this->company, [
            'date_range' => 'all',
            'report_keys' => ['client.name', 'invoice.amount', 'invoice.balance', 'invoice.paid_to_date'],
            'send_email' => false,
            'group_by' => 'client.name',
            'include_deleted' => false,
        ]);

        $result = $export->groupedReturnJson();
        $this->writeArtifact('group_by_invoice_by_client_json_structure.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // --- Columns structure ---
        $this->assertArrayHasKey('columns', $result);
        $columns = $result['columns'];

        // 4 report keys + 1 count column = 5
        $this->assertCount(5, $columns);

        // Verify column identifiers
        $identifiers = array_column($columns, 'identifier');
        $this->assertEquals(['client.name', 'invoice.amount', 'invoice.balance', 'invoice.paid_to_date', 'group.count'], $identifiers);

        // Verify column display values are localized headers
        $this->assertEquals('Client Name', $columns[0]['display_value']);
        $this->assertEquals('Count', $columns[4]['display_value']);

        // --- Data rows ---
        $data_rows = $this->extractJsonDataRows($result);
        $this->assertCount(2, $data_rows);

        // Find alpha and beta rows
        $alpha_row = collect($data_rows)->first(fn ($row) => $this->getJsonCellValue($row, 'client.name') === 'Client Alpha');
        $beta_row = collect($data_rows)->first(fn ($row) => $this->getJsonCellValue($row, 'client.name') === 'Client Beta');

        $this->assertNotNull($alpha_row);
        $this->assertNotNull($beta_row);

        // --- Alpha row values (100 + 200 = 300 amount, 40 + 80 = 120 balance, 60 + 120 = 180 paid) ---
        $this->assertEquals('300.00', $this->getJsonCellValue($alpha_row, 'invoice.amount'));
        $this->assertEquals('120.00', $this->getJsonCellValue($alpha_row, 'invoice.balance'));
        $this->assertEquals('180.00', $this->getJsonCellValue($alpha_row, 'invoice.paid_to_date'));
        $this->assertEquals(2, $this->getJsonCellValue($alpha_row, 'group.count'));

        // --- Beta row values (single invoice) ---
        $this->assertEquals('500.00', $this->getJsonCellValue($beta_row, 'invoice.amount'));
        $this->assertEquals('500.00', $this->getJsonCellValue($beta_row, 'invoice.balance'));
        $this->assertEquals('0.00', $this->getJsonCellValue($beta_row, 'invoice.paid_to_date'));
        $this->assertEquals(1, $this->getJsonCellValue($beta_row, 'group.count'));

        // --- Cell metadata structure ---
        $alpha_name_cell = collect($alpha_row)->firstWhere('identifier', 'client.name');
        $this->assertEquals('client', $alpha_name_cell['entity']);
        $this->assertEquals('name', $alpha_name_cell['id']);
        $this->assertNull($alpha_name_cell['hashed_id']);
        $this->assertEquals('Client Alpha', $alpha_name_cell['value']);
        $this->assertEquals('Client Alpha', $alpha_name_cell['display_value']);

        $alpha_count_cell = collect($alpha_row)->firstWhere('identifier', 'group.count');
        $this->assertEquals('group', $alpha_count_cell['entity']);
        $this->assertEquals('count', $alpha_count_cell['id']);
        $this->assertNull($alpha_count_cell['hashed_id']);
        $this->assertEquals(2, $alpha_count_cell['value']);
        $this->assertEquals('2', $alpha_count_cell['display_value']);
    }

    public function testJsonNonSummableColumnsReturnEmptyValue(): void
    {
        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 100.0,
            'balance' => 50.0,
            'exchange_rate' => 1.5,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 200.0,
            'balance' => 75.0,
            'exchange_rate' => 2.0,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $export = new InvoiceExport($this->company, [
            'date_range' => 'all',
            'report_keys' => ['client.name', 'invoice.amount', 'invoice.exchange_rate'],
            'send_email' => false,
            'group_by' => 'client.name',
            'include_deleted' => false,
        ]);

        $result = $export->groupedReturnJson();
        $this->writeArtifact('group_by_invoice_non_summable.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $data_rows = $this->extractJsonDataRows($result);
        $row = $data_rows[0];

        // Amount should be summed
        $this->assertEquals('300.00', $this->getJsonCellValue($row, 'invoice.amount'));

        // Exchange rate is non-summable — value must be empty
        $this->assertEquals('', $this->getJsonCellValue($row, 'invoice.exchange_rate'));
    }

    public function testJsonExpenseGroupByCategoryValues(): void
    {
        $travel = ExpenseCategory::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Travel',
        ]);

        $office = ExpenseCategory::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Office',
        ]);

        Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'category_id' => $travel->id,
            'amount' => 150.0,
        ]);

        Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'category_id' => $travel->id,
            'amount' => 250.0,
        ]);

        Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'category_id' => $office->id,
            'amount' => 75.0,
        ]);

        $export = new ExpenseExport($this->company, [
            'date_range' => 'all',
            'report_keys' => ['expense.category', 'expense.amount'],
            'send_email' => false,
            'group_by' => 'expense.category',
            'include_deleted' => false,
        ]);

        $result = $export->groupedReturnJson();
        $this->writeArtifact('group_by_expense_by_category_json_structure.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $data_rows = $this->extractJsonDataRows($result);

        $this->assertCount(2, $data_rows);

        $travel_row = collect($data_rows)->first(fn ($row) => $this->getJsonCellValue($row, 'expense.category') === 'Travel');
        $office_row = collect($data_rows)->first(fn ($row) => $this->getJsonCellValue($row, 'expense.category') === 'Office');

        $this->assertNotNull($travel_row);
        $this->assertNotNull($office_row);

        $this->assertEquals('400.00', $this->getJsonCellValue($travel_row, 'expense.amount'));
        $this->assertEquals(2, $this->getJsonCellValue($travel_row, 'group.count'));

        $this->assertEquals('75.00', $this->getJsonCellValue($office_row, 'expense.amount'));
        $this->assertEquals(1, $this->getJsonCellValue($office_row, 'group.count'));
    }

    // ---------------------------------------------------------------
    // Edge Cases & Behavior Tests
    // ---------------------------------------------------------------

    public function testGroupByEmptyStringIsInactive(): void
    {
        $export = new InvoiceExport($this->company, [
            'date_range' => 'all',
            'report_keys' => ['client.name', 'invoice.amount'],
            'send_email' => false,
            'group_by' => '',
            'include_deleted' => false,
        ]);

        $this->assertFalse($export->isGroupByActive());
    }

    public function testGroupByNullIsInactive(): void
    {
        $export = new InvoiceExport($this->company, [
            'date_range' => 'all',
            'report_keys' => ['client.name', 'invoice.amount'],
            'send_email' => false,
            'group_by' => null,
            'include_deleted' => false,
        ]);

        $this->assertFalse($export->isGroupByActive());
    }

    public function testGroupByKeyAutoAddedToReportKeys(): void
    {
        $data = [
            'date_range' => 'all',
            'report_keys' => ['invoice.amount', 'invoice.balance'],
            'send_email' => false,
            'group_by' => 'client.name',
            'include_deleted' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/invoices', $data);

        $response->assertStatus(200);
    }

    public function testGroupByApiEndpointReturnsHash(): void
    {
        Invoice::factory()->count(3)->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 100.0,
            'balance' => 50.0,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => ['client.name', 'invoice.amount', 'invoice.balance'],
            'send_email' => false,
            'group_by' => 'client.name',
            'include_deleted' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/invoices', $data);

        $response->assertStatus(200);
        $response->assertJsonStructure(['message']);
    }

    public function testGroupByWithSingleInvoicePerGroup(): void
    {
        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 100.0,
            'balance' => 100.0,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        Invoice::factory()->create([
            'client_id' => $this->client2->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 200.0,
            'balance' => 200.0,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $export = new InvoiceExport($this->company, [
            'date_range' => 'all',
            'report_keys' => ['client.name', 'invoice.amount'],
            'send_email' => false,
            'group_by' => 'client.name',
            'include_deleted' => false,
        ]);

        $csv = $export->groupedRun();
        $this->writeArtifact('group_by_invoice_single_per_group.csv', $csv);

        $rows = $this->parseCsvByFirstColumn($csv);

        // Single invoice per group — amounts should equal the original values
        $this->assertEquals('100.00', $rows['Client Alpha']['Invoice Amount']);
        $this->assertEquals('1', $rows['Client Alpha']['Count']);
        $this->assertEquals('200.00', $rows['Client Beta']['Invoice Amount']);
        $this->assertEquals('1', $rows['Client Beta']['Count']);
    }

    // ---------------------------------------------------------------
    // Mixed-type column resilience
    //
    // The previous detectNumericColumns() implementation classified an entire
    // column as numeric based on the FIRST non-empty sample row, then fed the
    // raw column to array_sum(). On PHP 8.3+ that throws
    // "TypeError: array_sum(): Addition is not supported on type string"
    // whenever a later row in the same column carried a non-numeric string.
    //
    // The free-text columns most exposed to this in production are
    // custom_value1..4, vat_number, id_number, postal_code, phone, number,
    // and po_number — all VARCHAR fields with no transformer-side casting.
    // The tests below exercise that surface and document the current behaviour:
    // mixed columns no longer throw; a column is summed only when every value
    // is numeric; otherwise it falls into the string path (preserved if all
    // rows agree, blanked if they differ).
    // ---------------------------------------------------------------

    public function testCsvHandlesMixedNumericAndNonNumericCustomValuesWithoutThrowing(): void
    {
        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 100.0,
            'balance' => 100.0,
            'custom_value1' => '100',
            'status_id' => Invoice::STATUS_SENT,
        ]);

        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 50.0,
            'balance' => 50.0,
            'custom_value1' => 'not-a-number',
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $export = new InvoiceExport($this->company, [
            'date_range' => 'all',
            'report_keys' => ['client.name', 'invoice.amount', 'invoice.custom_value1'],
            'send_email' => false,
            'group_by' => 'client.name',
            'include_deleted' => false,
        ]);

        $csv = $export->groupedRun();
        $rows = $this->parseCsvByFirstColumn($csv);

        $this->assertArrayHasKey('Client Alpha', $rows);
        $this->assertEquals('150.00', $rows['Client Alpha']['Invoice Amount']);
        // Mixed numeric + non-numeric: column is not fully numeric, so it falls
        // into the string path. Values differ ('100' vs 'not-a-number') → blank.
        // No partial sum is reported (which would be misleading).
        $this->assertEquals('', $rows['Client Alpha']['Invoice Custom Value 1']);
        $this->assertEquals('2', $rows['Client Alpha']['Count']);
    }

    public function testCsvAllNonNumericColumnYieldsBlank(): void
    {
        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 100.0,
            'balance' => 100.0,
            'custom_value1' => 'alpha',
            'status_id' => Invoice::STATUS_SENT,
        ]);

        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 50.0,
            'balance' => 50.0,
            'custom_value1' => 'beta',
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $export = new InvoiceExport($this->company, [
            'date_range' => 'all',
            'report_keys' => ['client.name', 'invoice.amount', 'invoice.custom_value1'],
            'send_email' => false,
            'group_by' => 'client.name',
            'include_deleted' => false,
        ]);

        $csv = $export->groupedRun();
        $rows = $this->parseCsvByFirstColumn($csv);

        $this->assertArrayHasKey('Client Alpha', $rows);
        $this->assertEquals('150.00', $rows['Client Alpha']['Invoice Amount']);
        $this->assertEquals('', $rows['Client Alpha']['Invoice Custom Value 1']);
    }

    public function testCsvFirstRowNumericLaterRowsNonNumericDoesNotThrow(): void
    {
        // Reproduces the exact pre-fix failure: first sample looks numeric so the
        // column was classified summable, then array_sum hit a string in a later row.
        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 10.0,
            'custom_value2' => '42',
            'status_id' => Invoice::STATUS_SENT,
        ]);

        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 20.0,
            'custom_value2' => 'see-notes',
            'status_id' => Invoice::STATUS_SENT,
        ]);

        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 30.0,
            'custom_value2' => '1,000.00', // locale-formatted, not is_numeric
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $export = new InvoiceExport($this->company, [
            'date_range' => 'all',
            'report_keys' => ['client.name', 'invoice.amount', 'invoice.custom_value2'],
            'send_email' => false,
            'group_by' => 'client.name',
            'include_deleted' => false,
        ]);

        // Pre-fix: TypeError. Post-fix: completes cleanly.
        $csv = $export->groupedRun();
        $rows = $this->parseCsvByFirstColumn($csv);

        $this->assertEquals('60.00', $rows['Client Alpha']['Invoice Amount']);
        // Column has '42', 'see-notes', '1,000.00' — not all numeric, so the
        // column falls into the string path. Values differ → blank.
        $this->assertEquals('', $rows['Client Alpha']['Invoice Custom Value 2']);
    }

    /**
     * Thesis verification: confirm that no amount-bearing column reaches
     * groupRows() as a non-numeric string. If this test ever fails, the
     * is_numeric() filter in groupRows() would silently drop real money
     * from the totals, and we would need a stricter coercion strategy
     * (e.g. (float) coercion) instead of array_filter('is_numeric').
     */
    public function testAmountColumnsArriveAtGroupRowsAsNumericValues(): void
    {
        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 123.45,
            'balance' => 67.89,
            'paid_to_date' => 55.55,
            'discount' => 5.0,
            'partial' => 10.0,
            'total_taxes' => 12.34,
            'tax_rate1' => 10.0,
            'exchange_rate' => 1.5,
            'custom_surcharge1' => 1.0,
            'custom_surcharge2' => 2.0,
            'custom_surcharge3' => 3.0,
            'custom_surcharge4' => 4.0,
            'status_id' => Invoice::STATUS_SENT,
            'is_amount_discount' => true,
        ]);

        $export = new InvoiceExport($this->company, [
            'date_range' => 'all',
            'report_keys' => [
                'client.name',
                'invoice.amount',
                'invoice.balance',
                'invoice.paid_to_date',
                'invoice.discount',
                'invoice.partial',
                'invoice.total_taxes',
                'invoice.tax_rate1',
                'invoice.exchange_rate',
                'invoice.custom_surcharge1',
                'invoice.custom_surcharge2',
                'invoice.custom_surcharge3',
                'invoice.custom_surcharge4',
            ],
            'send_email' => false,
            'group_by' => 'client.name',
            'include_deleted' => false,
        ]);

        $export->groupedRun();

        $reflection = new \ReflectionClass($export);
        $rawRowsProp = $reflection->getProperty('raw_rows');
        $rawRowsProp->setAccessible(true);
        $rawRows = $rawRowsProp->getValue($export);

        $this->assertNotEmpty($rawRows, 'raw_rows should be populated by groupedRun()');

        $row = $rawRows[0];

        $amountColumns = [
            'invoice.amount',
            'invoice.balance',
            'invoice.paid_to_date',
            'invoice.discount',
            'invoice.partial',
            'invoice.total_taxes',
            'invoice.tax_rate1',
            'invoice.exchange_rate',
            'invoice.custom_surcharge1',
            'invoice.custom_surcharge2',
            'invoice.custom_surcharge3',
            'invoice.custom_surcharge4',
        ];

        foreach ($amountColumns as $column) {
            $this->assertArrayHasKey($column, $row, "Missing amount column {$column}");

            $value = $row[$column];

            $this->assertTrue(
                is_float($value) || is_int($value) || (is_string($value) && is_numeric($value)),
                "Amount column {$column} arrived as non-numeric type "
                . gettype($value) . ' value=' . var_export($value, true)
                . '. The is_numeric() filter in groupRows() would silently drop this value.'
            );
        }
    }

    public function testNormalRunUnchangedWhenGroupByAbsent(): void
    {
        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 100.0,
            'balance' => 100.0,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => ['client.name', 'invoice.amount', 'invoice.balance'],
            'send_email' => false,
            'group_by' => '',
            'include_deleted' => false,
        ];

        $export = new InvoiceExport($this->company, $data);

        // Normal run should not be affected
        $csv = $export->run();
        $this->writeArtifact('group_by_invoice_normal_ungrouped.csv', $csv);

        $reader = Reader::createFromString($csv);
        $reader->setHeaderOffset(0);
        $records = iterator_to_array($reader->getRecords());

        // Should have 1 individual row (not grouped)
        $this->assertCount(1, $records);

        // Header should NOT have Count column
        $header = $reader->getHeader();
        $this->assertNotContains('Count', $header);
    }
}
