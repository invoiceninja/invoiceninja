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

namespace App\Console\Commands;

use App\DataMapper\CompanySettings;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Factory\ProjectFactory;
use App\Factory\QuoteFactory;
use App\Factory\RecurringExpenseFactory;
use App\Factory\RecurringInvoiceFactory;
use App\Factory\TaskFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Jobs\Company\CreateCompanyPaymentTerms;
use App\Jobs\Company\CreateCompanyTaskStatuses;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use App\Models\TaskStatus;
use App\Models\User;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesHash;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ==================================================================================
 * ANALYTICS TEST DATA COMMAND — FULL YEAR
 * ==================================================================================
 *
 * Creates a deterministic dataset spanning 12 months of the current year for
 * verifying analytics charts and tables.
 *
 * ----------------------------------------------------------------------------------
 * ACCOUNT / COMPANY / USER
 * ----------------------------------------------------------------------------------
 *   Company: "Analytics"
 *   Email:   analytics@example.com
 *   Clients: 5 — "Alpha Corp", "Beta LLC", "Gamma Inc", "Delta Partners", "Epsilon Co"
 *
 * ----------------------------------------------------------------------------------
 * INVOICES (36 total — 3 per month)
 * ----------------------------------------------------------------------------------
 *   Months 1-9: PAID with deterministic payment delays
 *     - 1st invoice each month: paid in 12 days (on time)
 *     - 2nd invoice each month: paid in 25 days (on time)
 *     - 3rd invoice each month: paid in 40 days (LATE — exceeds 30-day terms)
 *   Months 10-11: SENT (outstanding)
 *   Month 12: DRAFT
 *
 *   Payment timestamps on paymentables are also backdated to match.
 *
 * ----------------------------------------------------------------------------------
 * EXPENSES (24 total — 2 per month)
 * ----------------------------------------------------------------------------------
 *   Rotating categories and clients with varying amounts.
 *
 * ----------------------------------------------------------------------------------
 * QUOTES (12 total — 1 per month)
 * ----------------------------------------------------------------------------------
 *   First 8 months: APPROVED, last 4: SENT.
 *
 * ----------------------------------------------------------------------------------
 * PROJECTS (17 total) & TASKS (116 total)
 * ----------------------------------------------------------------------------------
 *   Five baseline client projects plus a larger 12-project portfolio dataset.
 *   The portfolio dataset includes multiple users, tasks, invoices, expenses,
 *   expense categories, varied due dates, and status distribution for dashboard
 *   visualizations.
 *
 * ----------------------------------------------------------------------------------
 * RECURRING INVOICES (3) & RECURRING EXPENSES (3)
 * ----------------------------------------------------------------------------------
 *   Provides MRR/ARR data.
 *
 * ----------------------------------------------------------------------------------
 * KEY ANALYTICS VERIFICATION POINTS
 * ----------------------------------------------------------------------------------
 *   avg_payment_days:  ~25.67 (avg of 12, 25, 40 across 9 months)
 *   late_payment_ratio: ~0.3333 (1 of 3 invoices late each month)
 *
 * ==================================================================================
 */
class CreateAnalyticsTestData extends Command
{
    use MakesHash;
    use GeneratesCounter;

    protected $signature = 'ninja:create-analytics-test-data {--refresh : Delete existing Analytics company and recreate}';

    protected $description = 'Create a curated, deterministic dataset for testing analytics endpoints';

    public function handle(): int
    {
        if (config('ninja.is_docker')) {
            $this->error('This command is not intended for Docker environments.');

            return self::FAILURE;
        }

        $existing = Company::where('settings->name', 'Analytics')->first();

        if ($existing && $this->option('refresh')) {
            $account = $existing->account;
            $account->delete();
            $this->info('Existing Analytics company deleted.');
        } elseif ($existing) {
            $this->error('A company named "Analytics" already exists. Use --refresh to delete and recreate.');

            return self::FAILURE;
        }

        if (! $this->option('refresh') && ! $this->confirm('This will create a new "Analytics" company with deterministic test data. Continue?')) {
            return self::SUCCESS;
        }

        $this->info('Creating Analytics company with full-year test data...');

        [$account, $company, $user] = $this->createAccountAndCompany();
        $clients = $this->createClients($company, $user);

        $projects = $this->createProjects($company, $user, $clients);
        $this->createInvoices($company, $user, $clients, $projects);
        $this->createQuotes($company, $user, $clients);
        $this->createExpenses($company, $user, $clients, $projects);
        $this->createLargeProjectDataset($company, $user, $clients);
        $this->createRecurringInvoices($company, $user, $clients);
        $this->createRecurringExpenses($company, $user, $clients);
        $this->linkProjectTasksAndExpenses($company);

        $this->newLine();
        $this->info('Analytics test data created successfully.');
        $this->info('Login: analytics@example.com');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * @return array{0: Account, 1: Company, 2: User}
     */
    private function createAccountAndCompany(): array
    {
        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $settings = $company->settings;
        $settings->name = 'Analytics';
        $company->settings = $settings;
        $company->save();

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => 'analytics@example.com',
            'confirmation_code' => $this->createDbHash(config('database.default')),
        ]);

        $company_token = new CompanyToken();
        $company_token->user_id = $user->id;
        $company_token->company_id = $company->id;
        $company_token->account_id = $account->id;
        $company_token->name = 'analytics test token';
        $company_token->token = Str::random(64);
        $company_token->is_system = true;
        $company_token->save();

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'notifications' => CompanySettings::notificationDefaults(),
            'settings' => null,
        ]);

        // Create default task statuses and payment terms (same as normal account creation)
        (new CreateCompanyPaymentTerms($company, $user))->handle();
        (new CreateCompanyTaskStatuses($company, $user))->handle();

        $this->info('  Account & company created (with task statuses and payment terms).');

        return [$account, $company, $user];
    }

    /**
     * @return array<string, Client>
     */
    private function createClients(Company $company, User $user): array
    {
        $clientNames = [
            'alpha' => 'Alpha Corp',
            'beta' => 'Beta LLC',
            'gamma' => 'Gamma Inc',
            'delta' => 'Delta Partners',
            'epsilon' => 'Epsilon Co',
        ];

        $clients = [];

        foreach ($clientNames as $key => $name) {
            $client = Client::factory()->create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'name' => $name,
                'country_id' => 840, // US
            ]);

            $settings = $client->settings;
            $settings->currency_id = '1'; // USD
            $client->settings = $settings;
            $client->save();

            ClientContact::factory()->create([
                'user_id' => $user->id,
                'client_id' => $client->id,
                'company_id' => $company->id,
                'is_primary' => 1,
            ]);

            $client->number = $this->getNextClientNumber($client);
            $client->save();

            $clients[$key] = $client;
        }

        $this->info('  5 clients created (Alpha Corp, Beta LLC, Gamma Inc, Delta Partners, Epsilon Co).');

        return $clients;
    }

    private function createInvoices(Company $company, User $user, array $clients, array $projects): void
    {
        $year = now()->year;
        $clientKeys = ['alpha', 'beta', 'gamma', 'delta', 'epsilon'];

        // 3 invoices per month: [amount_1, amount_2, amount_3]
        // Growth curve with seasonal variation for interesting charts
        $monthlyAmounts = [
            1  => [800, 600, 400],
            2  => [900, 700, 500],
            3  => [1000, 800, 600],
            4  => [1200, 900, 700],
            5  => [1500, 1100, 800],
            6  => [1800, 1300, 900],
            7  => [2000, 1500, 1000],
            8  => [1900, 1400, 950],
            9  => [1600, 1200, 850],
            10 => [2200, 1600, 1100],
            11 => [2500, 1800, 1200],
            12 => [2800, 2000, 1400],
        ];

        // Per-month payment delays for the 2 paid invoices (positions 0 and 1).
        // Position 2 (3rd invoice) stays SENT = outstanding.
        // Delays > 30 = LATE (due_date is invoice_date + 30).
        // Varying delays produce non-flat chart lines.
        $monthlyDelays = [
            1  => [8, 20],     // avg 14,   late 0/2 = 0%
            2  => [12, 35],    // avg 23.5, late 1/2 = 50%
            3  => [15, 28],    // avg 21.5, late 0/2 = 0%
            4  => [10, 42],    // avg 26,   late 1/2 = 50%
            5  => [18, 45],    // avg 31.5, late 1/2 = 50%
            6  => [22, 38],    // avg 30,   late 1/2 = 50%
            7  => [7, 15],     // avg 11,   late 0/2 = 0%
            8  => [25, 50],    // avg 37.5, late 1/2 = 50%
            9  => [14, 32],    // avg 23,   late 1/2 = 50%
            10 => [],          // no payments (all SENT)
            11 => [],          // no payments (all SENT)
            12 => [],          // no payments (all DRAFT)
        ];

        // Per-month status for the 3rd invoice (index 2):
        // Months 1-11: SENT (outstanding), Month 12: DRAFT
        // Months 10-11: ALL 3 invoices are SENT (no payments)

        $invoiceIndex = 0;
        $paidCount = 0;
        $sentCount = 0;
        $draftCount = 0;
        $lateCount = 0;
        $totalInvoiced = 0;
        $totalPaid = 0;
        $totalOutstanding = 0;

        foreach ($monthlyAmounts as $month => $amounts) {
            $delays = $monthlyDelays[$month];

            foreach ($amounts as $i => $baseAmount) {
                $clientKey = $clientKeys[($month + $i) % count($clientKeys)];
                $client = $clients[$clientKey];
                $invoiceIndex++;
                $number = str_pad($invoiceIndex, 4, '0', STR_PAD_LEFT);

                $date = Carbon::createFromDate($year, $month, min(10 + ($i * 7), 28))->format('Y-m-d');

                $invoice = InvoiceFactory::create($company->id, $user->id);
                $invoice->client_id = $client->id;
                $invoice->date = $date;
                $invoice->due_date = Carbon::parse($date)->addDays(30)->format('Y-m-d');
                $invoice->number = "ANA-INV-{$number}";
                $invoice->uses_inclusive_taxes = false;
                $invoice->tax_name1 = 'GST';
                $invoice->tax_rate1 = 10.00;
                $invoice->line_items = $this->buildLineItem(1, (float) $baseAmount, 'Service Item');

                // Link first invoice of each month to the client's project (for profitability)
                if ($i === 0 && isset($projects[$clientKey])) {
                    $invoice->project_id = $projects[$clientKey]->id;
                }

                $invoice->save();

                $invoice_calc = new InvoiceSum($invoice);
                $invoice_calc->build();
                $invoice = $invoice_calc->getInvoice();
                $invoice->save();

                $invoice->service()->createInvitations();

                $invoiceTotal = $invoice->amount;
                $totalInvoiced += $invoiceTotal;

                // Determine status: PAID if delay exists for this position, SENT, or DRAFT
                if ($month <= 9 && $i < 2) {
                    // PAID — first 2 invoices in months 1-9
                    $invoice->service()->markSent()->save();
                    $invoice->service()->markPaid()->save();

                    $delay = $delays[$i];
                    $paymentDate = Carbon::parse($date)->addDays($delay)->format('Y-m-d');
                    $paymentTimestamp = Carbon::parse($paymentDate)->startOfDay();

                    $payment = $invoice->payments()->first();
                    if ($payment) {
                        $payment->date = $paymentDate;
                        $payment->saveQuietly();

                        DB::table('paymentables')
                            ->where('payment_id', $payment->id)
                            ->where('paymentable_type', 'invoices')
                            ->where('paymentable_id', $invoice->id)
                            ->update([
                                'created_at' => $paymentTimestamp,
                                'updated_at' => $paymentTimestamp,
                            ]);
                    }

                    if ($delay > 30) {
                        $lateCount++;
                    }

                    $paidCount++;
                    $totalPaid += $invoiceTotal;
                } elseif ($month <= 11) {
                    // SENT — 3rd invoice each month (1-9), all invoices (10-11)
                    $invoice->service()->markSent()->save();
                    $sentCount++;
                    $totalOutstanding += $invoice->balance;
                } else {
                    // DRAFT — month 12
                    $draftCount++;
                }
            }
        }

        $this->info("  {$invoiceIndex} invoices created ({$paidCount} paid, {$sentCount} sent, {$draftCount} draft).");
        $this->info("  Late payments: {$lateCount} of {$paidCount} paid.");
        $this->info("  Total invoiced: \$" . number_format($totalInvoiced, 2));
        $this->info("  Total paid: \$" . number_format($totalPaid, 2));
        $this->info("  Total outstanding (sent): \$" . number_format($totalOutstanding, 2));
    }

    private function createQuotes(Company $company, User $user, array $clients): void
    {
        $year = now()->year;
        $clientKeys = ['alpha', 'beta', 'gamma', 'delta', 'epsilon'];

        $quoteAmounts = [
            1 => 1500, 2 => 1800, 3 => 2000, 4 => 2200,
            5 => 2500, 6 => 3000, 7 => 3200, 8 => 2800,
            9 => 2600, 10 => 3500, 11 => 3800, 12 => 4000,
        ];

        $quoteIndex = 0;

        foreach ($quoteAmounts as $month => $amount) {
            $clientKey = $clientKeys[$month % count($clientKeys)];
            $client = $clients[$clientKey];
            $quoteIndex++;
            $number = str_pad($quoteIndex, 4, '0', STR_PAD_LEFT);

            $date = Carbon::createFromDate($year, $month, 5)->format('Y-m-d');

            $quote = QuoteFactory::create($company->id, $user->id);
            $quote->client_id = $client->id;
            $quote->date = $date;
            $quote->due_date = Carbon::parse($date)->addDays(30)->format('Y-m-d');
            $quote->number = "ANA-QUO-{$number}";
            $quote->uses_inclusive_taxes = false;
            $quote->tax_name1 = 'GST';
            $quote->tax_rate1 = 10.00;
            $quote->line_items = $this->buildLineItem(1, (float) $amount, 'Quoted Service');

            $quote->save();

            $quote->setRelation('client', $client);

            $quote_calc = new InvoiceSum($quote);
            $quote_calc->build();
            $quote = $quote_calc->getQuote();
            $quote->save();

            $quote->service()->createInvitations();
            $quote->service()->markSent()->save();

            // First 8 months: APPROVED, rest: SENT
            if ($month <= 8) {
                $quote->status_id = Quote::STATUS_APPROVED;
                $quote->save();
            }
        }

        $this->info("  {$quoteIndex} quotes created (8 approved, 4 sent).");
    }

    private function createExpenses(Company $company, User $user, array $clients, array $projects): void
    {
        $year = now()->year;
        $clientKeys = ['alpha', 'beta', 'gamma', 'delta', 'epsilon'];

        $categories = ['Office Supplies', 'Software Licenses', 'Travel', 'Consulting', 'Cloud Hosting', 'Marketing'];

        // 2 expenses per month with varying amounts
        $monthlyExpenses = [
            1  => [400, 300],
            2  => [500, 350],
            3  => [450, 400],
            4  => [600, 500],
            5  => [700, 550],
            6  => [800, 600],
            7  => [900, 650],
            8  => [850, 600],
            9  => [750, 500],
            10 => [950, 700],
            11 => [1000, 750],
            12 => [1100, 800],
        ];

        $expenseIndex = 0;
        $totalExpenses = 0;

        foreach ($monthlyExpenses as $month => $amounts) {
            foreach ($amounts as $i => $amount) {
                $clientKey = $clientKeys[($month + $i) % count($clientKeys)];
                $client = $clients[$clientKey];
                $expenseIndex++;
                $number = str_pad($expenseIndex, 4, '0', STR_PAD_LEFT);
                $category = $categories[($month + $i) % count($categories)];

                $date = Carbon::createFromDate($year, $month, 5 + ($i * 12))->format('Y-m-d');

                $expense = new Expense();
                $expense->user_id = $user->id;
                $expense->company_id = $company->id;
                $expense->client_id = $client->id;
                $expense->date = $date;
                $expense->amount = $amount;
                $expense->public_notes = $category;

                // Link first expense of each month to the client's project
                if ($i === 0 && isset($projects[$clientKey])) {
                    $expense->project_id = $projects[$clientKey]->id;
                }
                $expense->private_notes = "Analytics test expense #{$number}";
                $expense->number = "ANA-EXP-{$number}";
                $expense->is_deleted = false;
                $expense->should_be_invoiced = false;
                $expense->uses_inclusive_taxes = false;
                $expense->tax_name1 = '';
                $expense->tax_rate1 = 0;
                $expense->tax_name2 = '';
                $expense->tax_rate2 = 0;
                $expense->tax_name3 = '';
                $expense->tax_rate3 = 0;
                $expense->tax_amount1 = 0;
                $expense->tax_amount2 = 0;
                $expense->tax_amount3 = 0;
                $expense->foreign_amount = 0;
                $expense->exchange_rate = 1;
                $expense->currency_id = 1;
                $expense->transaction_reference = '';
                $expense->custom_value1 = '';
                $expense->custom_value2 = '';
                $expense->custom_value3 = '';
                $expense->custom_value4 = '';
                $expense->save();

                $totalExpenses += $amount;
            }
        }

        $this->info("  {$expenseIndex} expenses created. Total: \$" . number_format($totalExpenses, 2));
    }

    /**
     * @return array<string, \App\Models\Project>
     */
    private function createProjects(Company $company, User $user, array $clients): array
    {
        $projectsByClient = [];
        $year = now()->year;

        // Fetch the task statuses created by CreateCompanyTaskStatuses
        $statuses = TaskStatus::where('company_id', $company->id)
            ->orderBy('status_order')
            ->pluck('id', 'status_order')
            ->toArray();

        // status_order: 1=Backlog, 2=Ready to do, 3=In progress, 4=Done
        $doneStatusId = $statuses[4] ?? null;
        $inProgressStatusId = $statuses[3] ?? null;
        $backlogStatusId = $statuses[1] ?? null;

        $projectData = [
            // [client_key, name, task_rate, budgeted_hours, due_date, completed]
            ['alpha', 'Website Redesign', 120.00, 80.0, "{$year}-06-30", true],
            ['beta', 'Mobile App Development', 150.00, 200.0, "{$year}-09-30", false],
            ['gamma', 'Data Migration', 100.00, 40.0, "{$year}-04-30", true],
            ['delta', 'API Integration', 130.00, 60.0, "{$year}-08-31", false],
            ['epsilon', 'Security Audit', 175.00, 30.0, "{$year}-05-31", true],
        ];

        // Each project gets 4 tasks matching the 4 statuses (for completed projects all Done,
        // for in-progress projects a mix of statuses)
        $taskTemplates = [
            // [description_suffix, hours_per_entry, entries_count]
            ['Requirements & Planning', 4, 2],
            ['Design & Architecture', 6, 3],
            ['Implementation', 8, 4],
            ['Testing & QA', 3, 2],
        ];

        $projectCount = 0;
        $taskCount = 0;
        $totalHoursLogged = 0;

        foreach ($projectData as $index => $row) {
            [$clientKey, $name, $taskRate, $budgetedHours, $dueDate, $completed] = $row;
            $client = $clients[$clientKey];

            $project = ProjectFactory::create($company->id, $user->id);
            $project->client_id = $client->id;
            $project->name = $name;
            $project->task_rate = $taskRate;
            $project->budgeted_hours = $budgetedHours;
            $project->due_date = $dueDate;
            $project->public_notes = "Analytics test project for {$client->name}";
            $project->color = ['#4A90D9', '#7B68EE', '#2ECC71', '#E67E22', '#E74C3C'][$index];
            $project->save();

            $project->number = $this->getNextProjectNumber($project);
            $project->save();

            $projectCount++;

            foreach ($taskTemplates as $taskIndex => $taskTemplate) {
                [$taskSuffix, $hoursPerEntry, $entriesCount] = $taskTemplate;
                $taskCount++;

                $task = TaskFactory::create($company->id, $user->id);
                $task->client_id = $client->id;
                $task->project_id = $project->id;
                $task->description = "{$taskSuffix} — {$name}";
                $task->rate = $taskRate;
                $task->is_running = false;
                $task->is_deleted = false;

                // Assign status: completed projects = all Done;
                // in-progress projects = first 2 Done, 3rd In Progress, 4th Ready/Backlog
                if ($completed) {
                    $task->status_id = $doneStatusId;
                } else {
                    $task->status_id = match ($taskIndex) {
                        0, 1 => $doneStatusId,
                        2 => $inProgressStatusId,
                        3 => $backlogStatusId,
                    };
                }

                $task->save();
                $task->number = $this->getNextTaskNumber($task);

                // Build time_log entries spread across the year
                $timeLog = [];
                $startMonth = max(1, ($index * 2) + 1);
                for ($entry = 0; $entry < $entriesCount; $entry++) {
                    $logMonth = min($startMonth + $taskIndex + $entry, 12);
                    $logDay = min(3 + ($entry * 7), 28);
                    $startTime = Carbon::createFromDate($year, $logMonth, $logDay)
                        ->setHour(9)
                        ->setMinute(0)
                        ->setSecond(0)
                        ->timestamp;
                    $endTime = $startTime + ($hoursPerEntry * 3600);
                    $timeLog[] = [$startTime, $endTime];
                }

                $task->time_log = json_encode($timeLog);
                $task->duration = collect($timeLog)->sum(fn($e) => $e[1] - $e[0]);
                $task->calculated_start_date = Carbon::createFromTimestamp($timeLog[0][0])->format('Y-m-d');
                $task->save();

                $totalHoursLogged += $task->duration / 3600;
            }

            // Update project current_hours from task durations
            $projectHours = (int) round(
                \App\Models\Task::where('project_id', $project->id)->sum('duration') / 3600
            );
            $project->current_hours = $projectHours;
            $project->save();

            $projectsByClient[$clientKey] = $project;
        }

        $this->info("  {$projectCount} projects created with {$taskCount} tasks ({$totalHoursLogged}h logged).");

        return $projectsByClient;
    }

    /**
     * Stamp the backward relations that the application itself relies on when work is invoiced.
     *
     * Invoice Ninja invoices a project's tasks/expenses by setting `invoice_id` on each record
     * (see InvoiceService::linkEntities() and ProjectRepository). The test data already links
     * invoices and expenses to projects via `project_id`; here we complete the chain so that
     * project analytics can distinguish invoiced vs. unbilled work:
     *
     *   - "Done" tasks (status_order 4) are linked to the project's earliest non-draft invoice,
     *     mirroring the real-world flow where completed work is what gets billed. Tasks in other
     *     statuses stay uninvoiced so unbilled_hours / unbilled_value remain non-zero.
     *   - Every second invoiceable expense (should_be_invoiced = true) is linked to the same
     *     invoice; the remainder stay pending to exercise the "pending" expense status.
     *
     * Amounts and line items are intentionally left untouched — only the FK is set, exactly as
     * the application does via a bulk update.
     */
    private function linkProjectTasksAndExpenses(Company $company): void
    {
        $linkedTasks = 0;
        $linkedExpenses = 0;

        \App\Models\Project::query()
            ->where('company_id', $company->id)
            ->where('is_deleted', false)
            ->cursor()
            ->each(function (\App\Models\Project $project) use (&$linkedTasks, &$linkedExpenses): void {
                $invoice = $project->invoices()
                    ->where('is_deleted', false)
                    ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID])
                    ->orderBy('date')
                    ->first();

                if (! $invoice) {
                    return;
                }

                $doneTaskIds = $project->tasks()
                    ->where('is_deleted', false)
                    ->whereHas('status', fn ($query) => $query->where('status_order', '>=', 4))
                    ->pluck('id');

                if ($doneTaskIds->isNotEmpty()) {
                    \App\Models\Task::query()
                        ->whereIn('id', $doneTaskIds)
                        ->update(['invoice_id' => $invoice->id]);

                    $linkedTasks += $doneTaskIds->count();
                }

                $invoiceableExpenseIds = $project->expenses()
                    ->where('is_deleted', false)
                    ->where('should_be_invoiced', true)
                    ->whereNull('invoice_id')
                    ->whereNull('payment_date')
                    ->orderBy('id')
                    ->pluck('id')
                    ->filter(fn ($id, $index) => $index % 2 === 0)
                    ->values();

                if ($invoiceableExpenseIds->isNotEmpty()) {
                    Expense::query()
                        ->whereIn('id', $invoiceableExpenseIds)
                        ->update(['invoice_id' => $invoice->id]);

                    $linkedExpenses += $invoiceableExpenseIds->count();
                }
            });

        $this->info("  Linked {$linkedTasks} done tasks and {$linkedExpenses} expenses to their project invoices.");
    }

    private function createLargeProjectDataset(Company $company, User $owner, array $clients): void
    {
        $year = now()->year;
        $teamUsers = $this->createAnalyticsTeamUsers($company, $owner);
        $categories = $this->createProjectExpenseCategories($company, $owner);

        $statuses = TaskStatus::where('company_id', $company->id)
            ->orderBy('status_order')
            ->pluck('id', 'status_order')
            ->toArray();

        $projectTemplates = [
            ['alpha', 'Portfolio Analytics — CRM Migration', 145.00, 260.0, "{$year}-07-31", '#2F80ED'],
            ['beta', 'Portfolio Analytics — Data Warehouse', 165.00, 420.0, "{$year}-10-15", '#27AE60'],
            ['gamma', 'Portfolio Analytics — Billing Automation', 135.00, 180.0, "{$year}-05-30", '#9B51E0'],
            ['delta', 'Portfolio Analytics — Mobile Portal', 155.00, 320.0, "{$year}-11-20", '#F2994A'],
            ['epsilon', 'Portfolio Analytics — Security Program', 175.00, 210.0, "{$year}-06-20", '#EB5757'],
            ['alpha', 'Portfolio Analytics — Client Reporting', 125.00, 150.0, "{$year}-04-30", '#56CCF2'],
            ['beta', 'Portfolio Analytics — Platform Upgrade', 150.00, 280.0, "{$year}-08-31", '#6FCF97'],
            ['gamma', 'Portfolio Analytics — Integration Hub', 140.00, 240.0, "{$year}-09-15", '#BB6BD9'],
            ['delta', 'Portfolio Analytics — Vendor Onboarding', 120.00, 130.0, "{$year}-03-31", '#F2C94C'],
            ['epsilon', 'Portfolio Analytics — Compliance Review', 180.00, 170.0, "{$year}-12-15", '#219653'],
            ['alpha', 'Portfolio Analytics — AI Search Pilot', 190.00, 220.0, "{$year}-11-30", '#2D9CDB'],
            ['beta', 'Portfolio Analytics — Support Operations', 115.00, 160.0, "{$year}-06-05", '#828282'],
        ];

        $taskTemplates = [
            ['Discovery', 1],
            ['Architecture', 2],
            ['Implementation A', 4],
            ['Implementation B', 4],
            ['Client Review', 3],
            ['Quality Assurance', 2],
            ['Deployment', 1],
            ['Stabilization', 3],
        ];

        $projectCount = 0;
        $taskCount = 0;
        $invoiceCount = 0;
        $expenseCount = 0;
        $totalHoursLogged = 0.0;

        foreach ($projectTemplates as $projectIndex => $template) {
            [$clientKey, $name, $taskRate, $budgetedHours, $dueDate, $color] = $template;
            $client = $clients[$clientKey];
            $projectOwner = $teamUsers[$projectIndex % count($teamUsers)];

            $project = ProjectFactory::create($company->id, $projectOwner->id);
            $project->client_id = $client->id;
            $project->assigned_user_id = $teamUsers[($projectIndex + 1) % count($teamUsers)]->id;
            $project->name = $name;
            $project->task_rate = $taskRate;
            $project->budgeted_hours = $budgetedHours;
            $project->due_date = $dueDate;
            $project->public_notes = "Large analytics visualization project for {$client->name}";
            $project->color = $color;
            $project->save();

            $project->number = $this->getNextProjectNumber($project);
            $project->save();

            $projectCount++;

            foreach ($taskTemplates as $taskIndex => $taskTemplate) {
                [$taskSuffix, $statusOrder] = $taskTemplate;
                $taskUser = $teamUsers[($projectIndex + $taskIndex) % count($teamUsers)];
                $hoursPerEntry = 2 + (($projectIndex + $taskIndex) % 5);
                $entriesCount = 2 + (($projectIndex + ($taskIndex * 2)) % 3);
                $startMonth = (($projectIndex + $taskIndex) % 10) + 1;
                $timeLog = [];

                for ($entry = 0; $entry < $entriesCount; $entry++) {
                    $logMonth = min($startMonth + $entry, 12);
                    $logDay = min(4 + ($taskIndex * 2) + ($entry * 6), 28);
                    $startTime = Carbon::createFromDate($year, $logMonth, $logDay)
                        ->setHour(8 + (($taskIndex + $entry) % 3))
                        ->setMinute(0)
                        ->setSecond(0)
                        ->timestamp;
                    $endTime = $startTime + ($hoursPerEntry * 3600);
                    $timeLog[] = [$startTime, $endTime];
                }

                $task = TaskFactory::create($company->id, $taskUser->id);
                $task->assigned_user_id = $teamUsers[($projectIndex + $taskIndex + 1) % count($teamUsers)]->id;
                $task->client_id = $client->id;
                $task->project_id = $project->id;
                $task->description = "{$taskSuffix} — {$name}";
                $task->rate = $taskRate + (($taskIndex % 3) * 10);
                $task->status_id = $statuses[$statusOrder] ?? null;
                $task->time_log = json_encode($timeLog);
                $task->duration = collect($timeLog)->sum(fn ($entry) => $entry[1] - $entry[0]);
                $task->calculated_start_date = Carbon::createFromTimestamp($timeLog[0][0])->format('Y-m-d');
                $task->is_running = false;
                $task->is_deleted = false;
                $task->save();

                $task->number = $this->getNextTaskNumber($task);
                $task->save();

                $taskCount++;
                $totalHoursLogged += $task->duration / 3600;
            }

            $project->current_hours = (int) round(
                \App\Models\Task::where('project_id', $project->id)->sum('duration') / 3600
            );
            $project->save();

            for ($invoiceIndex = 0; $invoiceIndex < 3; $invoiceIndex++) {
                $invoiceCount++;
                $this->createLargeProjectInvoice(
                    $company,
                    $projectOwner,
                    $client,
                    $project,
                    $projectIndex,
                    $invoiceIndex,
                    $year
                );
            }

            for ($expenseIndex = 0; $expenseIndex < 5; $expenseIndex++) {
                $expenseCount++;
                $this->createLargeProjectExpense(
                    $company,
                    $teamUsers[($projectIndex + $expenseIndex) % count($teamUsers)],
                    $client,
                    $project,
                    $categories[$expenseIndex % count($categories)],
                    $projectIndex,
                    $expenseIndex,
                    $year
                );
            }
        }

        $this->info("  Large project dataset: {$projectCount} projects, {$taskCount} tasks, {$invoiceCount} invoices, {$expenseCount} expenses ({$totalHoursLogged}h logged).");
    }

    /**
     * @return array<int, User>
     */
    private function createAnalyticsTeamUsers(Company $company, User $owner): array
    {
        $team = [$owner];
        $users = [
            ['analytics.pm@example.com', 'Priya', 'Morgan'],
            ['analytics.dev@example.com', 'Devon', 'Stone'],
            ['analytics.qa@example.com', 'Quinn', 'Taylor'],
            ['analytics.ops@example.com', 'Owen', 'Reed'],
        ];

        foreach ($users as $userData) {
            [$email, $firstName, $lastName] = $userData;

            $user = User::factory()->create([
                'account_id' => $company->account_id,
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'confirmation_code' => $this->createDbHash(config('database.default')),
            ]);

            $user->companies()->attach($company->id, [
                'account_id' => $company->account_id,
                'is_owner' => 0,
                'is_admin' => 0,
                'is_locked' => 0,
                'notifications' => CompanySettings::notificationDefaults(),
                'settings' => null,
            ]);

            $team[] = $user;
        }

        $this->info('  4 additional analytics project users created.');

        return $team;
    }

    /**
     * @return array<int, ExpenseCategory>
     */
    private function createProjectExpenseCategories(Company $company, User $user): array
    {
        $categories = [];
        $data = [
            ['Cloud Infrastructure', '#2F80ED'],
            ['Subcontractors', '#27AE60'],
            ['Travel & Workshops', '#F2994A'],
            ['Software Tooling', '#9B51E0'],
            ['Hardware & Devices', '#EB5757'],
        ];

        foreach ($data as $row) {
            [$name, $color] = $row;

            $category = new ExpenseCategory();
            $category->company_id = $company->id;
            $category->user_id = $user->id;
            $category->name = $name;
            $category->color = $color;
            $category->is_deleted = false;
            $category->save();

            $categories[] = $category;
        }

        return $categories;
    }

    private function createLargeProjectInvoice(
        Company $company,
        User $user,
        Client $client,
        \App\Models\Project $project,
        int $projectIndex,
        int $invoiceIndex,
        int $year
    ): void {
        $month = (($projectIndex + $invoiceIndex) % 12) + 1;
        $date = Carbon::createFromDate($year, $month, min(6 + ($invoiceIndex * 9), 28))->format('Y-m-d');
        $number = str_pad(($projectIndex * 3) + $invoiceIndex + 1, 4, '0', STR_PAD_LEFT);
        $amount = 1200 + ($projectIndex * 175) + ($invoiceIndex * 450);

        $invoice = InvoiceFactory::create($company->id, $user->id);
        $invoice->client_id = $client->id;
        $invoice->project_id = $project->id;
        $invoice->date = $date;
        $invoice->due_date = Carbon::parse($date)->addDays(30)->format('Y-m-d');
        $invoice->number = "ANA-BIG-INV-{$number}";
        $invoice->uses_inclusive_taxes = false;
        $invoice->tax_name1 = '';
        $invoice->tax_rate1 = 0;
        $invoice->line_items = $this->buildLineItem(1, (float) $amount, 'Project Milestone');
        $invoice->save();

        $invoiceCalc = new InvoiceSum($invoice);
        $invoiceCalc->build();
        $invoice = $invoiceCalc->getInvoice();

        if ($invoiceIndex === 0) {
            $invoice->status_id = Invoice::STATUS_SENT;
            $invoice->paid_to_date = 0;
            $invoice->balance = $invoice->amount;
        } elseif ($invoiceIndex === 1) {
            $invoice->status_id = Invoice::STATUS_PARTIAL;
            $invoice->paid_to_date = round($invoice->amount * 0.35, 2);
            $invoice->balance = max($invoice->amount - $invoice->paid_to_date, 0);
        } else {
            $invoice->status_id = Invoice::STATUS_DRAFT;
            $invoice->paid_to_date = 0;
            $invoice->balance = $invoice->amount;
        }

        $invoice->save();
    }

    private function createLargeProjectExpense(
        Company $company,
        User $user,
        Client $client,
        \App\Models\Project $project,
        ExpenseCategory $category,
        int $projectIndex,
        int $expenseIndex,
        int $year
    ): void {
        $month = (($projectIndex * 2) + $expenseIndex) % 12 + 1;
        $date = Carbon::createFromDate($year, $month, min(4 + ($expenseIndex * 5), 28))->format('Y-m-d');
        $number = str_pad(($projectIndex * 5) + $expenseIndex + 1, 4, '0', STR_PAD_LEFT);
        $amount = 180 + ($projectIndex * 35) + ($expenseIndex * 90);

        $expense = new Expense();
        $expense->user_id = $user->id;
        $expense->company_id = $company->id;
        $expense->client_id = $client->id;
        $expense->project_id = $project->id;
        $expense->category_id = $category->id;
        $expense->date = $date;
        $expense->amount = $amount;
        $expense->foreign_amount = 0;
        $expense->exchange_rate = 1;
        $expense->currency_id = 1;
        $expense->public_notes = $category->name;
        $expense->private_notes = "Large analytics project expense #{$number}";
        $expense->number = "ANA-BIG-EXP-{$number}";
        $expense->is_deleted = false;
        $expense->should_be_invoiced = $expenseIndex % 2 === 0;
        $expense->uses_inclusive_taxes = false;
        $expense->tax_name1 = '';
        $expense->tax_rate1 = 0;
        $expense->tax_name2 = '';
        $expense->tax_rate2 = 0;
        $expense->tax_name3 = '';
        $expense->tax_rate3 = 0;
        $expense->tax_amount1 = 0;
        $expense->tax_amount2 = 0;
        $expense->tax_amount3 = 0;
        $expense->transaction_reference = '';
        $expense->custom_value1 = '';
        $expense->custom_value2 = '';
        $expense->custom_value3 = '';
        $expense->custom_value4 = '';
        $expense->save();
    }

    private function createRecurringInvoices(Company $company, User $user, array $clients): void
    {
        $recurringData = [
            // [client_key, qty, unit_cost, status, remaining_cycles]
            ['alpha', 10, 100.00, RecurringInvoice::STATUS_ACTIVE, 12],
            ['beta', 5, 100.00, RecurringInvoice::STATUS_ACTIVE, 6],
            ['gamma', 20, 100.00, RecurringInvoice::STATUS_PAUSED, 3],
        ];

        foreach ($recurringData as $index => $row) {
            [$clientKey, $qty, $cost, $statusId, $cycles] = $row;
            $client = $clients[$clientKey];
            $number = str_pad($index + 1, 4, '0', STR_PAD_LEFT);

            $ri = RecurringInvoiceFactory::create($company->id, $user->id);
            $ri->client_id = $client->id;
            $ri->number = "ANA-REC-{$number}";
            $ri->status_id = $statusId;
            $ri->frequency_id = RecurringInvoice::FREQUENCY_MONTHLY;
            $ri->remaining_cycles = $cycles;
            $ri->next_send_date = now()->addMonthNoOverflow()->format('Y-m-d');
            $ri->next_send_date_client = now()->addMonthNoOverflow()->format('Y-m-d');
            $ri->uses_inclusive_taxes = false;
            $ri->tax_name1 = 'GST';
            $ri->tax_rate1 = 10.00;
            $ri->line_items = $this->buildLineItem($qty, $cost, 'Recurring Service');
            $ri->save();

            $ri_calc = new InvoiceSum($ri);
            $ri_calc->build();
            $ri = $ri_calc->getRecurringInvoice();
            $ri->save();
        }

        $this->info('  3 recurring invoices created ($1,100 + $550 + $2,200 paused). Active monthly: $1,650.00.');
    }

    private function createRecurringExpenses(Company $company, User $user, array $clients): void
    {
        $recurringExpenseData = [
            // [client_key, amount, description]
            ['alpha', 200.00, 'SaaS Subscription'],
            ['beta', 350.00, 'Cloud Hosting'],
            ['gamma', 150.00, 'Support Contract'],
        ];

        foreach ($recurringExpenseData as $index => $row) {
            [$clientKey, $amount, $description] = $row;
            $client = $clients[$clientKey];
            $number = str_pad($index + 1, 4, '0', STR_PAD_LEFT);

            $re = RecurringExpenseFactory::create($company->id, $user->id);
            $re->client_id = $client->id;
            $re->number = "ANA-REXP-{$number}";
            $re->status_id = RecurringInvoice::STATUS_ACTIVE;
            $re->frequency_id = RecurringInvoice::FREQUENCY_MONTHLY;
            $re->amount = $amount;
            $re->public_notes = $description;
            $re->private_notes = "Analytics test recurring expense #{$number}";
            $re->next_send_date = now()->addMonthNoOverflow()->format('Y-m-d');
            $re->next_send_date_client = now()->addMonthNoOverflow()->format('Y-m-d');
            $re->remaining_cycles = -1; // indefinite
            $re->currency_id = 1;
            $re->save();
        }

        $this->info('  3 recurring expenses created ($200 + $350 + $150). Monthly total: $700.00.');
    }

    /**
     * Build a single-item line_items array with exact figures.
     */
    private function buildLineItem(int $quantity, float $cost, string $productKey): array
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = $quantity;
        $item->cost = $cost;
        $item->product_key = $productKey;
        $item->notes = "{$quantity} x \${$cost}";
        $item->tax_name1 = '';
        $item->tax_rate1 = 0;
        $item->tax_name2 = '';
        $item->tax_rate2 = 0;
        $item->tax_name3 = '';
        $item->tax_rate3 = 0;
        $item->discount = 0;

        return [$item];
    }
}
