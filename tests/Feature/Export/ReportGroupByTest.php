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

    public function testCsvNonNumericColumnsAreBlankExceptGroupKey(): void
    {
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
        $this->writeArtifact('group_by_invoice_non_numeric_blank.csv', $csv);

        $rows = $this->parseCsvByFirstColumn($csv);

        $sent = $rows['Sent'];

        // The group key (status) has a value
        $this->assertEquals('Sent', $sent['Invoice Status']);

        // client.name is auto-prepended as a forced field — should be blank since it's a non-group string
        $this->assertEquals('', $sent['Client Name']);
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
