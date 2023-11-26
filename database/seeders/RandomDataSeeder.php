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

        $this->buildCache(true);

        $this->command->info('Running RandomDataSeeder');

        Model::unguard();

        $faker = \Faker\Factory::create();
        $settings= CompanySettings::defaults();
        
        $settings->name = "Random Test Company";
        $settings->currency_id = '1';
        $settings->language_id = '1';
        
        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
            'settings' => $settings,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::factory()->create([
            'email'             => $faker->freeEmail(),
            'account_id' => $account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
        ]);

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
                'email' => "{$p_user}@example.com",
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
            'email' => 'user@example.com',
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



        $client = Client::factory()->create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'name' => 'cypress'
            ]);

        $client->number = $client->getNextClientNumber($client);
        $client->save();
        
        ClientContact::factory()->create([
                    'user_id' => $user->id,
                    'client_id' => $client->id,
                    'company_id' => $company->id,
                    'is_primary' => 1,
                    'email' => 'cypress@example.com',
                    'password' => Hash::make('password'),
                ]);



        $vendor = Vendor::factory()->create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'name' => 'cypress'
            ]);

        $vendor->number = $vendor->getNextVendorNumber($vendor);
        $vendor->save();
        
        VendorContact::factory()->create([
                    'user_id' => $user->id,
                    'vendor_id' => $vendor->id,
                    'company_id' => $company->id,
                    'is_primary' => 1,
                    'email' => 'cypress_vendor@example.com',
                    'password' => Hash::make('password'),
                ]);



        /* Product Factory */
        Product::factory()->count(2)->create(['user_id' => $user->id, 'company_id' => $company->id]);

        /* Invoice Factory */
        Invoice::factory()->count(2)->create(['user_id' => $user->id, 'company_id' => $company->id, 'client_id' => $client->id]);

        $invoices = Invoice::all();
        $invoice_repo = new InvoiceRepository();

        $invoices->each(function ($invoice) use ($invoice_repo, $user, $company, $client) {
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

                // $payment_hash = new PaymentHash;
                // $payment_hash->hash = Str::random(128);
                // $payment_hash->data = [['invoice_id' => $invoice->hashed_id, 'amount' => $invoice->balance]];
                // $payment_hash->fee_total = 0;
                // $payment_hash->fee_invoice_id = $invoice->id;
                // $payment_hash->save();

                event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars()));

                // $payment->service()->updateInvoicePayment($payment_hash);

            }
        });

        /*Credits*/
        Credit::factory()->count(2)->create(['user_id' => $user->id, 'company_id' => $company->id, 'client_id' => $client->id]);

        $credits = Credit::cursor();
        $credit_repo = new CreditRepository();

        $credits->each(function ($credit) use ($credit_repo, $user, $company, $client) {
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

        $quotes = Quote::cursor();
        $quote_repo = new QuoteRepository();

        $quotes->each(function ($quote) use ($quote_repo, $user, $company, $client) {
            $quote_calc = null;

            if ($quote->uses_inclusive_taxes) {
                $quote_calc = new InvoiceSumInclusive($quote);
            } else {
                $quote_calc = new InvoiceSum($quote);
            }

            $quote = $quote_calc->build()->getQuote();

            $quote->save();

            //event(new CreateQuoteInvitation($quote));
            $quote->service()->createInvitations()->markSent()->save();
            //$invoice->markSent()->save();
        });

        GroupSetting::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'settings' =>  ClientSettings::buildClientSettings(CompanySettings::defaults(), ClientSettings::defaults()),
            'name' => 'Default Client Settings',
        ]);

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
