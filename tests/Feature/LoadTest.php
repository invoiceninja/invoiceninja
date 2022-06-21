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

use App\DataMapper\CompanySettings;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\Country;
use App\Models\Credit;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Repositories\InvoiceRepository;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class LoadTest extends TestCase
{
    use MakesHash;
    use GeneratesCounter;

    public int $count = 1;

    protected function setUp() :void
    {
        parent::setUp();

        $this->markTestSkipped('Skip test not needed in this environment');
    }

    public function testLoad()
    {
        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
            'slack_webhook_url' => config('ninja.notification.slack'),
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::whereEmail('small@example.com')->first();

        if (! $user) {
            $user = User::factory()->create([
                'account_id' => $account->id,
                'email' => 'small@example.com',
                'confirmation_code' => $this->createDbHash(config('database.default')),
            ]);
        }

        $company_token = new CompanyToken;
        $company_token->user_id = $user->id;
        $company_token->company_id = $company->id;
        $company_token->account_id = $account->id;
        $company_token->name = 'test token';
        $company_token->token = Str::random(64);
        $company_token->is_system = true;

        $company_token->save();

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'notifications' => CompanySettings::notificationDefaults(),
            // 'permissions' => '',
            'settings' => null,
        ]);

        dispatch(function () use ($user, $company) {
            Product::factory()->count(500)->create([
                'user_id' => $user->id,
                'company_id' => $company->id,
            ]);
        });

        for ($x = 0; $x < $this->count * 100; $x++) {
            $z = $x + 1;

            $this->createClient($company, $user);
        }

        do {
            sleep(3);
        } while ($company->clients()->count() != 500);

        for ($x = 0; $x < $this->count * 100; $x++) {
            $client = $company->clients->random();

            $this->createInvoice($client);

            $client = $company->clients->random();

            $this->createCredit($client);

            $client = $company->clients->random();

            $this->createQuote($client);

            $client = $company->clients->random();

            $this->createExpense($client);

            $client = $company->clients->random();

            $this->createVendor($client);

            $client = $company->clients->random();

            $this->createTask($client);

            $client = $company->clients->random();

            $this->createProject($client);
        }
    }

    private function createClient($company, $user)
    {
        dispatch(function () use ($company, $user) {
            $client = Client::factory()->create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'country_id' => 840,
            ]);

            Document::factory()->count(2)->create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'documentable_type' => Client::class,
                'documentable_id' => $client->id,
            ]);

            ClientContact::factory()->create([
                'user_id' => $user->id,
                'client_id' => $client->id,
                'company_id' => $company->id,
                'is_primary' => 1,
            ]);

            ClientContact::factory()->count(2)->create([
                'user_id' => $user->id,
                'client_id' => $client->id,
                'company_id' => $company->id,
            ]);

            $client->number = Str::random(28);

            $settings = $client->settings;
            $settings->currency_id = (string) rand(1, 79);
            $client->settings = $settings;
            $client->save();
        });
    }

    private function createExpense($client)
    {
        dispatch(function () use ($client) {
            Expense::factory()->count(rand(1, 5))->create([
                'user_id' => $client->user->id,
                'client_id' => $client->id,
                'company_id' => $client->company->id,
            ]);
        });
    }

    private function createVendor($client)
    {
        dispatch(function () use ($client) {
            $vendor = Vendor::factory()->create([
                'user_id' => $client->user->id,
                'company_id' => $client->company->id,
            ]);

            Document::factory()->count(2)->create([
                'user_id' => $client->user->id,
                'company_id' => $client->company_id,
                'documentable_type' => Vendor::class,
                'documentable_id' => $vendor->id,
            ]);

            VendorContact::factory()->create([
                'user_id' => $client->user->id,
                'vendor_id' => $vendor->id,
                'company_id' => $client->company->id,
                'is_primary' => 1,
            ]);

            VendorContact::factory()->count(rand(1, 500))->create([
                'user_id' => $client->user->id,
                'vendor_id' => $vendor->id,
                'company_id' => $client->company->id,
                'is_primary' => 0,
            ]);
        });
    }

    private function createTask($client)
    {
        dispatch(function () use ($client) {
            $vendor = Task::factory()->create([
                'user_id' => $client->user->id,
                'company_id' => $client->company->id,
            ]);

            Document::factory()->count(5)->create([
                'user_id' => $client->user->id,
                'company_id' => $client->company_id,
                'documentable_type' => Task::class,
                'documentable_id' => $vendor->id,
            ]);
        });
    }

    private function createProject($client)
    {
        dispatch(function () use ($client) {
            $vendor = Project::factory()->create([
                'user_id' => $client->user->id,
                'company_id' => $client->company->id,
            ]);

            Document::factory()->count(5)->create([
                'user_id' => $client->user->id,
                'company_id' => $client->company_id,
                'documentable_type' => Project::class,
                'documentable_id' => $vendor->id,
            ]);
        });
    }

    private function createInvoice($client)
    {
        $faker = \Faker\Factory::create();

        $invoice = InvoiceFactory::create($client->company->id, $client->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;
        $dateable = \Carbon\Carbon::now()->subDays(rand(0, 90));
        $invoice->date = $dateable;

        $invoice->line_items = $this->buildLineItems(rand(1, 10));
        $invoice->uses_inclusive_taxes = false;

        if (rand(0, 1)) {
            $invoice->tax_name1 = 'GST';
            $invoice->tax_rate1 = 10.00;
        }

        if (rand(0, 1)) {
            $invoice->tax_name2 = 'VAT';
            $invoice->tax_rate2 = 17.50;
        }

        if (rand(0, 1)) {
            $invoice->tax_name3 = 'CA Sales Tax';
            $invoice->tax_rate3 = 5;
        }

        $invoice->custom_value1 = $faker->date();
        $invoice->custom_value2 = rand(0, 1) ? 'yes' : 'no';

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();

        $invoice->save();
        $invoice->service()->createInvitations()->markSent();

        if (rand(0, 1)) {
            $invoice_repo = new InvoiceRepository();
            $invoice_repo->markSent($invoice);
        }

        if (rand(0, 1)) {
            $invoice = $invoice->service()->markPaid()->save();
        }

        Document::factory()->count(5)->create([
            'user_id' => $invoice->user->id,
            'company_id' => $invoice->company_id,
            'documentable_type' => Invoice::class,
            'documentable_id' => $invoice->id,
        ]);
    }

    private function createCredit($client)
    {
        $faker = \Faker\Factory::create();

        $credit = Credit::factory()->create(['user_id' => $client->user->id, 'company_id' => $client->company->id, 'client_id' => $client->id]);

        $dateable = \Carbon\Carbon::now()->subDays(rand(0, 90));
        $credit->date = $dateable;

        $credit->line_items = $this->buildLineItems(rand(1, 10));
        $credit->uses_inclusive_taxes = false;

        if (rand(0, 1)) {
            $credit->tax_name1 = 'GST';
            $credit->tax_rate1 = 10.00;
        }

        if (rand(0, 1)) {
            $credit->tax_name2 = 'VAT';
            $credit->tax_rate2 = 17.50;
        }

        if (rand(0, 1)) {
            $credit->tax_name3 = 'CA Sales Tax';
            $credit->tax_rate3 = 5;
        }

        $credit->save();

        $invoice_calc = new InvoiceSum($credit);
        $invoice_calc->build();

        $credit = $invoice_calc->getCredit();

        $credit->save();
        $credit->service()->markSent()->save();
        $credit->service()->createInvitations();
    }

    private function createQuote($client)
    {
        $faker = \Faker\Factory::create();

        //$quote = QuoteFactory::create($client->company->id, $client->user->id);//stub the company and user_id
        $quote = Quote::factory()->create(['user_id' => $client->user->id, 'company_id' => $client->company->id, 'client_id' => $client->id]);
        $quote->date = $faker->date();
        $quote->client_id = $client->id;

        $quote->setRelation('client', $client);

        $quote->line_items = $this->buildLineItems(rand(1, 10));
        $quote->uses_inclusive_taxes = false;

        if (rand(0, 1)) {
            $quote->tax_name1 = 'GST';
            $quote->tax_rate1 = 10.00;
        }

        if (rand(0, 1)) {
            $quote->tax_name2 = 'VAT';
            $quote->tax_rate2 = 17.50;
        }

        if (rand(0, 1)) {
            $quote->tax_name3 = 'CA Sales Tax';
            $quote->tax_rate3 = 5;
        }

        $quote->save();

        $quote_calc = new InvoiceSum($quote);
        $quote_calc->build();

        $quote = $quote_calc->getQuote();

        $quote->save();

        $quote->service()->markSent()->save();
        $quote->service()->createInvitations();
    }

    private function buildLineItems($count = 1)
    {
        $line_items = [];

        for ($x = 0; $x < $count; $x++) {
            $item = InvoiceItemFactory::create();
            $item->quantity = 1;
            //$item->cost = 10;

            if (rand(0, 1)) {
                $item->tax_name1 = 'GST';
                $item->tax_rate1 = 10.00;
            }

            if (rand(0, 1)) {
                $item->tax_name1 = 'VAT';
                $item->tax_rate1 = 17.50;
            }

            if (rand(0, 1)) {
                $item->tax_name1 = 'Sales Tax';
                $item->tax_rate1 = 5;
            }

            $product = Product::all()->random();

            $item->cost = (float) $product->cost;
            $item->product_key = $product->product_key;
            $item->notes = $product->notes;
            $item->custom_value1 = $product->custom_value1;
            $item->custom_value2 = $product->custom_value2;
            $item->custom_value3 = $product->custom_value3;
            $item->custom_value4 = $product->custom_value4;

            $line_items[] = $item;
        }

        return $line_items;
    }
}
