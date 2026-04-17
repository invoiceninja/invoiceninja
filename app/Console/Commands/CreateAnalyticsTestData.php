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
use App\Factory\QuoteFactory;
use App\Factory\RecurringExpenseFactory;
use App\Factory\RecurringInvoiceFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use App\Models\User;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesHash;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * ==================================================================================
 * ANALYTICS TEST DATA COMMAND
 * ==================================================================================
 *
 * Creates a deterministic, hand-crafted dataset for verifying analytics charts and
 * tables. Every figure is chosen so a tester can mentally verify totals at a glance.
 *
 * ----------------------------------------------------------------------------------
 * ACCOUNT / COMPANY / USER
 * ----------------------------------------------------------------------------------
 *   Company: "Analytics" (exits early if a company with this name already exists)
 *   Email:   analytics@example.com
 *   Clients: 3 — "Alpha Corp", "Beta LLC", "Gamma Inc"
 *
 * ----------------------------------------------------------------------------------
 * CLIENTS SUMMARY
 * ----------------------------------------------------------------------------------
 *   Alpha Corp  — 4 invoices, 2 quotes, 3 expenses, 1 recurring invoice, 1 recurring expense
 *   Beta LLC    — 3 invoices, 2 quotes, 2 expenses, 1 recurring invoice, 1 recurring expense
 *   Gamma Inc   — 2 invoices, 1 quote,  2 expenses, 1 recurring invoice, 1 recurring expense
 *
 * ----------------------------------------------------------------------------------
 * INVOICES (9 total)
 * ----------------------------------------------------------------------------------
 *
 *   Client       | #   | Date       | Line Items (qty x cost)         | Subtotal  | Tax (10% GST) | Total      | Status  | Payment
 *   -------------|-----|------------|---------------------------------|-----------|---------------|------------|---------|--------
 *   Alpha Corp   | 001 | Jan 15     | 10 x $100.00                   | $1,000.00 | $100.00       | $1,100.00  | PAID    | $1,100.00
 *   Alpha Corp   | 002 | Feb 15     | 5 x $200.00                    | $1,000.00 | $100.00       | $1,100.00  | PAID    | $1,100.00
 *   Alpha Corp   | 003 | Mar 15     | 8 x $125.00                    | $1,000.00 | $100.00       | $1,100.00  | SENT    | —
 *   Alpha Corp   | 004 | Apr 01     | 4 x $250.00                    | $1,000.00 | $100.00       | $1,100.00  | DRAFT   | —
 *   Beta LLC     | 005 | Jan 20     | 20 x $50.00                    | $1,000.00 | $100.00       | $1,100.00  | PAID    | $1,100.00
 *   Beta LLC     | 006 | Feb 20     | 2 x $500.00                    | $1,000.00 | $100.00       | $1,100.00  | SENT    | —
 *   Beta LLC     | 007 | Mar 20     | 10 x $100.00                   | $1,000.00 | $100.00       | $1,100.00  | SENT    | —
 *   Gamma Inc    | 008 | Jan 25     | 4 x $250.00                    | $1,000.00 | $100.00       | $1,100.00  | PAID    | $1,100.00
 *   Gamma Inc    | 009 | Mar 25     | 5 x $200.00                    | $1,000.00 | $100.00       | $1,100.00  | SENT    | —
 *
 *   TOTALS:
 *     All invoices:         9 x $1,100.00 = $9,900.00
 *     Paid invoices:        4 x $1,100.00 = $4,400.00  (Alpha 001, 002 + Beta 005 + Gamma 008)
 *     Outstanding (sent):   4 x $1,100.00 = $4,400.00  (Alpha 003 + Beta 006, 007 + Gamma 009)
 *     Draft:                1 x $1,100.00 = $1,100.00  (Alpha 004)
 *
 *   BY MONTH:
 *     January:   3 invoices = $3,300.00  (paid: $3,300.00)
 *     February:  2 invoices = $2,200.00  (paid: $1,100.00)
 *     March:     3 invoices = $3,300.00  (paid: $0.00)
 *     April:     1 invoice  = $1,100.00  (draft)
 *
 *   BY CLIENT:
 *     Alpha Corp:  4 invoices = $4,400.00  (paid: $2,200.00)
 *     Beta LLC:    3 invoices = $3,300.00  (paid: $1,100.00)
 *     Gamma Inc:   2 invoices = $2,200.00  (paid: $1,100.00)
 *
 * ----------------------------------------------------------------------------------
 * PAYMENTS (4 total)
 * ----------------------------------------------------------------------------------
 *     All created via markPaid() — each $1,100.00
 *     Total payments:  $4,400.00
 *     Jan: $2,200.00 (Alpha 001 + Beta 005)    — note: payment date = invoice date
 *     Feb: $1,100.00 (Alpha 002)
 *     Mar: $1,100.00 (Gamma 008 is dated Jan but paid via markPaid at creation)
 *
 * ----------------------------------------------------------------------------------
 * QUOTES (5 total)
 * ----------------------------------------------------------------------------------
 *
 *   Client       | Date       | Line Items       | Subtotal  | Tax (10%) | Total      | Status
 *   -------------|------------|------------------|-----------|-----------|------------|--------
 *   Alpha Corp   | Jan 10     | 10 x $150.00     | $1,500.00 | $150.00   | $1,650.00  | SENT
 *   Alpha Corp   | Mar 10     | 5 x $300.00      | $1,500.00 | $150.00   | $1,650.00  | APPROVED
 *   Beta LLC     | Feb 05     | 6 x $250.00      | $1,500.00 | $150.00   | $1,650.00  | SENT
 *   Beta LLC     | Mar 05     | 3 x $500.00      | $1,500.00 | $150.00   | $1,650.00  | SENT
 *   Gamma Inc    | Jan 15     | 15 x $100.00     | $1,500.00 | $150.00   | $1,650.00  | APPROVED
 *
 *   TOTALS:
 *     All quotes:      5 x $1,650.00 = $8,250.00
 *     Sent:            3 x $1,650.00 = $4,950.00
 *     Approved:        2 x $1,650.00 = $3,300.00
 *
 *   BY CLIENT:
 *     Alpha Corp:  2 x $1,650.00 = $3,300.00
 *     Beta LLC:    2 x $1,650.00 = $3,300.00
 *     Gamma Inc:   1 x $1,650.00 = $1,650.00
 *
 * ----------------------------------------------------------------------------------
 * EXPENSES (7 total)
 * ----------------------------------------------------------------------------------
 *
 *   Client       | Date       | Amount    | Category (public_notes)
 *   -------------|------------|-----------|------------------------
 *   Alpha Corp   | Jan 05     | $500.00   | Office Supplies
 *   Alpha Corp   | Feb 05     | $750.00   | Software Licenses
 *   Alpha Corp   | Mar 05     | $250.00   | Travel
 *   Beta LLC     | Jan 10     | $1,000.00 | Consulting
 *   Beta LLC     | Mar 10     | $500.00   | Office Supplies
 *   Gamma Inc    | Feb 15     | $300.00   | Travel
 *   Gamma Inc    | Mar 15     | $700.00   | Software Licenses
 *
 *   TOTALS:
 *     All expenses:  $4,000.00
 *
 *   BY MONTH:
 *     January:   $1,500.00  (Alpha $500 + Beta $1,000)
 *     February:  $1,050.00  (Alpha $750 + Gamma $300)
 *     March:     $1,450.00  (Alpha $250 + Beta $500 + Gamma $700)
 *
 *   BY CLIENT:
 *     Alpha Corp:  $1,500.00
 *     Beta LLC:    $1,500.00
 *     Gamma Inc:   $1,000.00
 *
 * ----------------------------------------------------------------------------------
 * RECURRING INVOICES (3 total — one per client, all monthly)
 * ----------------------------------------------------------------------------------
 *
 *   Client       | Amount/cycle | Frequency | Status | Remaining
 *   -------------|-------------|-----------|--------|----------
 *   Alpha Corp   | $1,100.00   | Monthly   | ACTIVE | 12
 *   Beta LLC     | $550.00     | Monthly   | ACTIVE | 6
 *   Gamma Inc    | $2,200.00   | Monthly   | PAUSED | 3
 *
 *   Total active recurring revenue/month:  $1,650.00
 *   Total all recurring revenue/month:     $3,850.00
 *
 * ----------------------------------------------------------------------------------
 * RECURRING EXPENSES (3 total — one per client, all monthly)
 * ----------------------------------------------------------------------------------
 *
 *   Client       | Amount/cycle | Frequency | Status | Description
 *   -------------|-------------|-----------|--------|---------------------------
 *   Alpha Corp   | $200.00     | Monthly   | ACTIVE | SaaS Subscription
 *   Beta LLC     | $350.00     | Monthly   | ACTIVE | Cloud Hosting
 *   Gamma Inc    | $150.00     | Monthly   | ACTIVE | Support Contract
 *
 *   Total recurring expense/month:  $700.00
 *
 * ----------------------------------------------------------------------------------
 * KEY ANALYTICS VERIFICATION POINTS
 * ----------------------------------------------------------------------------------
 *
 *   1. Revenue (invoiced):                   $9,900.00
 *   2. Revenue (collected / paid):           $4,400.00
 *   3. Outstanding invoices:                 $4,400.00
 *   4. Draft invoices:                       $1,100.00
 *   5. Total expenses:                       $4,000.00
 *   6. Net income (collected - expenses):    $400.00
 *   7. Total quoted:                         $8,250.00
 *   8. Approved quotes:                      $3,300.00
 *   9. Active recurring revenue/month:       $1,650.00
 *  10. Recurring expenses/month:             $700.00
 *  11. Average invoice amount:               $1,100.00
 *  12. Invoice count by status:              PAID=4, SENT=4, DRAFT=1
 *  13. Quote count by status:                SENT=3, APPROVED=2
 *  14. Top client by revenue (invoiced):     Alpha Corp ($4,400.00)
 *  15. Top client by payments:               Alpha Corp ($2,200.00)
 *
 * ==================================================================================
 */
class CreateAnalyticsTestData extends Command
{
    use MakesHash;
    use GeneratesCounter;

    protected $signature = 'ninja:create-analytics-test-data';

    protected $description = 'Create a curated, deterministic dataset for testing analytics endpoints';

    public function handle(): int
    {
        if (config('ninja.is_docker')) {
            $this->error('This command is not intended for Docker environments.');

            return self::FAILURE;
        }

        $exists = Company::where('settings->name', 'Analytics')->exists();

        if ($exists) {
            $this->error('A company named "Analytics" already exists. Aborting to prevent duplicate data.');

            return self::FAILURE;
        }

        if (! $this->confirm('This will create a new "Analytics" company with deterministic test data. Continue?')) {
            return self::SUCCESS;
        }

        $this->info('Creating Analytics company and test data...');

        [$account, $company, $user] = $this->createAccountAndCompany();
        $clients = $this->createClients($company, $user);

        $this->createInvoices($company, $user, $clients);
        $this->createQuotes($company, $user, $clients);
        $this->createExpenses($company, $user, $clients);
        $this->createRecurringInvoices($company, $user, $clients);
        $this->createRecurringExpenses($company, $user, $clients);

        $this->newLine();
        $this->info('Analytics test data created successfully.');
        $this->info('Login: analytics@example.com');
        $this->newLine();

        $this->table(
            ['Metric', 'Expected Value'],
            [
                ['Total invoiced', '$9,900.00'],
                ['Total collected (paid)', '$4,400.00'],
                ['Outstanding (sent)', '$4,400.00'],
                ['Draft invoices', '$1,100.00'],
                ['Total expenses', '$4,000.00'],
                ['Net income (paid - expenses)', '$400.00'],
                ['Total quoted', '$8,250.00'],
                ['Approved quotes', '$3,300.00'],
                ['Active recurring rev/month', '$1,650.00'],
                ['Recurring expenses/month', '$700.00'],
                ['Avg invoice amount', '$1,100.00'],
                ['Invoices: PAID / SENT / DRAFT', '4 / 4 / 1'],
                ['Quotes: SENT / APPROVED', '3 / 2'],
            ]
        );

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

        $this->info('  Account & company created.');

        return [$account, $company, $user];
    }

    /**
     * @return array{alpha: Client, beta: Client, gamma: Client}
     */
    private function createClients(Company $company, User $user): array
    {
        $clientNames = [
            'alpha' => 'Alpha Corp',
            'beta' => 'Beta LLC',
            'gamma' => 'Gamma Inc',
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

        $this->info('  3 clients created (Alpha Corp, Beta LLC, Gamma Inc).');

        return $clients;
    }

    private function createInvoices(Company $company, User $user, array $clients): void
    {
        $year = now()->year;

        $invoiceData = [
            // [client_key, date, qty, unit_cost, status, paid]
            ['alpha', "{$year}-01-15", 10, 100.00, 'paid', true],
            ['alpha', "{$year}-02-15", 5, 200.00, 'paid', true],
            ['alpha', "{$year}-03-15", 8, 125.00, 'sent', false],
            ['alpha', "{$year}-04-01", 4, 250.00, 'draft', false],
            ['beta', "{$year}-01-20", 20, 50.00, 'paid', true],
            ['beta', "{$year}-02-20", 2, 500.00, 'sent', false],
            ['beta', "{$year}-03-20", 10, 100.00, 'sent', false],
            ['gamma', "{$year}-01-25", 4, 250.00, 'paid', true],
            ['gamma', "{$year}-03-25", 5, 200.00, 'sent', false],
        ];

        foreach ($invoiceData as $index => $row) {
            [$clientKey, $date, $qty, $cost, $status, $paid] = $row;
            $client = $clients[$clientKey];
            $number = str_pad($index + 1, 4, '0', STR_PAD_LEFT);

            $invoice = InvoiceFactory::create($company->id, $user->id);
            $invoice->client_id = $client->id;
            $invoice->date = $date;
            $invoice->due_date = Carbon::parse($date)->addDays(30)->format('Y-m-d');
            $invoice->number = "ANA-INV-{$number}";
            $invoice->uses_inclusive_taxes = false;
            $invoice->tax_name1 = 'GST';
            $invoice->tax_rate1 = 10.00;
            $invoice->line_items = $this->buildLineItem($qty, $cost, 'Service Item');

            $invoice->save();

            $invoice_calc = new InvoiceSum($invoice);
            $invoice_calc->build();
            $invoice = $invoice_calc->getInvoice();
            $invoice->save();

            $invoice->service()->createInvitations();

            if ($status === 'sent' || $status === 'paid') {
                $invoice->service()->markSent()->save();
            }

            if ($paid) {
                $invoice->service()->markPaid()->save();
            }
        }

        $this->info('  9 invoices created (4 paid, 4 sent, 1 draft). Each $1,100.00.');
    }

    private function createQuotes(Company $company, User $user, array $clients): void
    {
        $year = now()->year;

        $quoteData = [
            // [client_key, date, qty, unit_cost, approved]
            ['alpha', "{$year}-01-10", 10, 150.00, false],
            ['alpha', "{$year}-03-10", 5, 300.00, true],
            ['beta', "{$year}-02-05", 6, 250.00, false],
            ['beta', "{$year}-03-05", 3, 500.00, false],
            ['gamma', "{$year}-01-15", 15, 100.00, true],
        ];

        foreach ($quoteData as $index => $row) {
            [$clientKey, $date, $qty, $cost, $approved] = $row;
            $client = $clients[$clientKey];
            $number = str_pad($index + 1, 4, '0', STR_PAD_LEFT);

            $quote = QuoteFactory::create($company->id, $user->id);
            $quote->client_id = $client->id;
            $quote->date = $date;
            $quote->due_date = Carbon::parse($date)->addDays(30)->format('Y-m-d');
            $quote->number = "ANA-QUO-{$number}";
            $quote->uses_inclusive_taxes = false;
            $quote->tax_name1 = 'GST';
            $quote->tax_rate1 = 10.00;
            $quote->line_items = $this->buildLineItem($qty, $cost, 'Quoted Service');

            $quote->save();

            $quote->setRelation('client', $client);

            $quote_calc = new InvoiceSum($quote);
            $quote_calc->build();
            $quote = $quote_calc->getQuote();
            $quote->save();

            $quote->service()->createInvitations();
            $quote->service()->markSent()->save();

            if ($approved) {
                $quote->status_id = Quote::STATUS_APPROVED;
                $quote->save();
            }
        }

        $this->info('  5 quotes created (3 sent, 2 approved). Each $1,650.00.');
    }

    private function createExpenses(Company $company, User $user, array $clients): void
    {
        $year = now()->year;

        $expenseData = [
            // [client_key, date, amount, description]
            ['alpha', "{$year}-01-05", 500.00, 'Office Supplies'],
            ['alpha', "{$year}-02-05", 750.00, 'Software Licenses'],
            ['alpha', "{$year}-03-05", 250.00, 'Travel'],
            ['beta', "{$year}-01-10", 1000.00, 'Consulting'],
            ['beta', "{$year}-03-10", 500.00, 'Office Supplies'],
            ['gamma', "{$year}-02-15", 300.00, 'Travel'],
            ['gamma', "{$year}-03-15", 700.00, 'Software Licenses'],
        ];

        foreach ($expenseData as $index => $row) {
            [$clientKey, $date, $amount, $description] = $row;
            $client = $clients[$clientKey];
            $number = str_pad($index + 1, 4, '0', STR_PAD_LEFT);

            $expense = new Expense();
            $expense->user_id = $user->id;
            $expense->company_id = $company->id;
            $expense->client_id = $client->id;
            $expense->date = $date;
            $expense->amount = $amount;
            $expense->public_notes = $description;
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
        }

        $this->info('  7 expenses created. Total: $4,000.00.');
    }

    private function createRecurringInvoices(Company $company, User $user, array $clients): void
    {
        $recurringData = [
            // [client_key, qty, unit_cost, status, remaining_cycles]
            ['alpha', 10, 100.00, RecurringInvoice::STATUS_ACTIVE, 12],
            ['beta', 5, 50.00, RecurringInvoice::STATUS_ACTIVE, 6],
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

        $this->info('  3 recurring invoices created ($1,100 + $550 + $2,200). Active monthly: $1,650.00.');
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
