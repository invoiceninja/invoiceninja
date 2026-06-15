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

namespace Database\Seeders;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\DataMapper\FeesAndLimits;
use App\Events\Payment\PaymentWasCreated;
use App\Helpers\Invoice\InvoiceSum;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Models\Account;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\CompanyToken;
use App\Models\Credit;
use App\Models\GroupSetting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\Product;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Repositories\CreditRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\QuoteRepository;
use App\Utils\Ninja;
use App\Utils\Traits\AppSetup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RandomDataSeeder extends Seeder
{
    use \App\Utils\Traits\MakesHash;
    use AppSetup;
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        $this->command->info('Running RandomDataSeeder');

        Model::unguard();

        $faker = \Faker\Factory::create();

        for ($x = 1; $x <= 8; $x++) {
            $this->seedAccount($faker, $x);
        }
    }

    private function seedAccount(\Faker\Generator $faker, int $x): void
    {
        if (User::where('email', "user{$x}@example.com")->exists()) {
            $this->command->info("Skipping account {$x}: user{$x}@example.com already exists");

            return;
        }

        $settings = CompanySettings::defaults();

        $settings->name = "Random Test Company {$x}";
        $settings->currency_id = '1';
        $settings->language_id = '1';

        /** @var \App\Models\Account $account */
        $account = Account::factory()->create();
        /** @var \App\Models\Company $company */
        $company = Company::factory()->create([
            'account_id' => $account->id,
            'settings' => $settings,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        /** @var \App\Models\User $user */
        $user = User::factory()->create([
            'email'             => $faker->freeEmail(),
            'account_id' => $account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
        ]);

        /** @var \App\Models\CompanyToken $company_token */
        $company_token = CompanyToken::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'account_id' => $account->id,
            'name' => 'test token',
            'token' => \Illuminate\Support\Str::random(64),
            'is_system' => 1
        ]);

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'notifications' => CompanySettings::notificationDefaults(),
            'permissions' => '',
            'settings' => null,
        ]);

        $permission_users = [
            'permissions',
            'products',
            'invoices',
            'quotes',
            'clients',
            'vendors',
            'tasks',
            'expenses',
            'projects',
            'credits',
            'payments',
            'bank_transactions',
            'purchase_orders',
        ];

        foreach ($permission_users as $p_user) {

            $user = User::firstOrNew([
                'email' => "{$p_user}{$x}@example.com",
            ]);

            $user->first_name = ucfirst($p_user);
            $user->last_name = 'Example';
            $user->password = Hash::make('password');
            $user->account_id = $account->id;
            $user->email_verified_at = now();
            $user->save();

            $company_token = CompanyToken::create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'account_id' => $account->id,
                'name' => 'test token',
                'token' => \Illuminate\Support\Str::random(64),
                'is_system' => 1,
            ]);

            $user->companies()->attach($company->id, [
                'account_id' => $account->id,
                'is_owner' => 0,
                'is_admin' => 0,
                'is_locked' => 0,
                'notifications' => CompanySettings::notificationDefaults(),
                'permissions' => '',
                'settings' => null,
            ]);

            $user = null;
        }


        $user = User::firstOrNew([
            'email' => "user{$x}@example.com",
        ]);

        $user->first_name = 'U';
        $user->last_name = 'ser';
        $user->password = Hash::make('password');
        $user->account_id = $account->id;
        $user->email_verified_at = now();
        $user->save();

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'notifications' => CompanySettings::notificationDefaults(),
            'permissions' => '',
            'settings' => null,
        ]);

        $company_token = CompanyToken::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'account_id' => $account->id,
            'name' => 'test token',
            'token' => \Illuminate\Support\Str::random(64),
            'is_system' => 1,
        ]);

        /** @var \App\Models\Client $client */
        $client = Client::factory()->create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'name' => 'cypress'
            ]);

        $client->number = $client->getNextClientNumber($client);
        $client->save();
        
        $billing_context = new \App\DataMapper\Billing\BillingContext();
        $billing_context->client_id = $client->id;
        
        $account->billing_context = $billing_context;
        $account->save();

        ClientContact::factory()->create([
                    'user_id' => $user->id,
                    'client_id' => $client->id,
                    'company_id' => $company->id,
                    'is_primary' => 1,
                    'email' => "cypress{$x}@example.com",
                    'password' => Hash::make('password'),
                ]);

        /** @var \App\Models\Vendor $vendor */
        $vendor = Vendor::factory()->create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'name' => 'cypress'
            ]);

        $vendor->number = $vendor->getNextVendorNumber($vendor);
        $vendor->save();
        
        /** @var \App\Models\VendorContact $vendor_contact */
        $vendor_contact = VendorContact::factory()->create([
                    'user_id' => $user->id,
                    'vendor_id' => $vendor->id,
                    'company_id' => $company->id,
                    'is_primary' => 1,
                    'email' => "cypress_vendor{$x}@example.com",
                    'password' => Hash::make('password'),
                ]);



        /* Product Factory */
        Product::factory()->count(2)->create(['user_id' => $user->id, 'company_id' => $company->id]);

        /* Invoice Factory */
        Invoice::factory()->count(2)->create(['user_id' => $user->id, 'company_id' => $company->id, 'client_id' => $client->id]);

        $invoices = Invoice::query()->where('company_id', $company->id)->get();

        $invoices->each(function ($invoice) use ($user, $company, $client) {
            $invoice_calc = null;

            if ($invoice->uses_inclusive_taxes) {
                $invoice_calc = new InvoiceSumInclusive($invoice);
            } else {
                $invoice_calc = new InvoiceSum($invoice);
            }

            $invoice = $invoice_calc->build()->getInvoice();

            $invoice->service()->createInvitations()->markSent()->save();

            $invoice->ledger()->updateInvoiceBalance($invoice->balance);

            if (rand(0, 1)) {
                $payment = Payment::create([
                    'date' => now(),
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                    'client_id' => $client->id,
                    'amount' => $invoice->balance,
                    'transaction_reference' => rand(0, 500),
                    'type_id' => PaymentType::CREDIT_CARD_OTHER,
                    'status_id' => Payment::STATUS_COMPLETED,
                ]);

                $payment->invoices()->save($invoice);

                event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars()));

            }
        });

        /*Credits*/
        Credit::factory()->count(2)->create(['user_id' => $user->id, 'company_id' => $company->id, 'client_id' => $client->id]);

        $credits = Credit::query()->where('company_id', $company->id)
                        ->cursor()
                        ->each(function ($credit){
                            $credit_calc = null;

                            if ($credit->uses_inclusive_taxes) {
                                $credit_calc = new InvoiceSumInclusive($credit);
                            } else {
                                $credit_calc = new InvoiceSum($credit);
                            }

                            $credit = $credit_calc->build()->getCredit();

                            $credit->save();

                            $credit->service()->createInvitations()->markSent()->save();
                        });

        /* Recurring Invoice Factory */
        RecurringInvoice::factory()->create(['user_id' => $user->id, 'company_id' => $company->id, 'client_id' => $client->id]);

        /*Credits*/
        Quote::factory()->create(['user_id' => $user->id, 'company_id' => $company->id, 'client_id' => $client->id]);

        $quotes = Quote::query()->where('company_id', $company->id)
                        ->cursor()
                        ->each(function ($quote) {
                            $quote_calc = null;

                            if ($quote->uses_inclusive_taxes) {
                                $quote_calc = new InvoiceSumInclusive($quote);
                            } else {
                                $quote_calc = new InvoiceSum($quote);
                            }

                            $quote = $quote_calc->build()->getQuote();
                            
                            $quote->service()->createInvitations()->markSent()->save();

                        });

        GroupSetting::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'settings' =>  ClientSettings::buildClientSettings(CompanySettings::defaults(), ClientSettings::defaults()),
            'name' => 'Default Client Settings',
        ]);

        /** @var \App\Models\BankIntegration $bi */
        $bi = BankIntegration::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        if (config('ninja.testvars.stripe')) {
            $cg = new CompanyGateway;
            $cg->company_id = $company->id;
            $cg->user_id = $user->id;
            $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
            $cg->require_cvv = true;
            $cg->require_billing_address = true;
            $cg->require_shipping_address = true;
            $cg->update_details = true;
            $cg->config = encrypt(config('ninja.testvars.stripe'));

            $gateway_types = $cg->driver()->gatewayTypes();

            $fees_and_limits = new \stdClass;
            $fees_and_limits->{$gateway_types[0]} = new FeesAndLimits;

            $cg->fees_and_limits = $fees_and_limits;
            $cg->save();

            $cg = new CompanyGateway;
            $cg->company_id = $company->id;
            $cg->user_id = $user->id;
            $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
            $cg->require_cvv = true;
            $cg->require_billing_address = true;
            $cg->require_shipping_address = true;
            $cg->update_details = true;
            $cg->config = encrypt(config('ninja.testvars.stripe'));
            $cg->save();
        }

        if (config('ninja.testvars.paypal')) {
            $cg = new CompanyGateway;
            $cg->company_id = $company->id;
            $cg->user_id = $user->id;
            $cg->gateway_key = '38f2c48af60c7dd69e04248cbb24c36e';
            $cg->require_cvv = true;
            $cg->require_billing_address = true;
            $cg->require_shipping_address = true;
            $cg->update_details = true;
            $cg->config = encrypt(config('ninja.testvars.paypal'));
            $cg->save();
        }

        if (config('ninja.testvars.checkout')) {
            $cg = new CompanyGateway;
            $cg->company_id = $company->id;
            $cg->user_id = $user->id;
            $cg->gateway_key = '3758e7f7c6f4cecf0f4f348b9a00f456';
            $cg->require_cvv = true;
            $cg->require_billing_address = true;
            $cg->require_shipping_address = true;
            $cg->update_details = true;
            $cg->config = encrypt(config('ninja.testvars.checkout'));
            $cg->save();
        }

        if (config('ninja.testvars.authorize')) {
            $cg = new CompanyGateway;
            $cg->company_id = $company->id;
            $cg->user_id = $user->id;
            $cg->gateway_key = '3b6621f970ab18887c4f6dca78d3f8bb';
            $cg->require_cvv = true;
            $cg->require_billing_address = true;
            $cg->require_shipping_address = true;
            $cg->update_details = true;
            $cg->config = encrypt(config('ninja.testvars.authorize'));
            $cg->save();
        }
    }
}
