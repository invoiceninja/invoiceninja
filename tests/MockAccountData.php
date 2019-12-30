<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace Tests;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\DataMapper\DefaultSettings;
use App\Factory\ClientFactory;
use App\Factory\CompanyUserFactory;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceInvitationFactory;
use App\Factory\InvoiceItemFactory;
use App\Factory\InvoiceToRecurringInvoiceFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Jobs\Company\UpdateCompanyLedgerWithInvoice;
use App\Jobs\Invoice\CreateInvoiceInvitations;
use App\Models\Client;
use App\Models\CompanyGateway;
use App\Models\CompanyToken;
use App\Models\Credit;
use App\Models\GroupSetting;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Models\User;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Class MockAccountData
 * @package Tests\Unit
 */
trait MockAccountData
{

	use MakesHash;
	use GeneratesCounter;

	public $account;

	public $company;

	public $user;

	public $client;

    public $token;

	public function makeTestData()
	{

        /* Warm up the cache !*/
        $cached_tables = config('ninja.cached_tables');
        
        foreach ($cached_tables as $name => $class) {
            if (! Cache::has($name)) {
                // check that the table exists in case the migration is pending
                if (! Schema::hasTable((new $class())->getTable())) {
                    continue;
                }
                if ($name == 'payment_terms') {
                    $orderBy = 'num_days';
                } elseif ($name == 'fonts') {
                    $orderBy = 'sort_order';
                } elseif (in_array($name, ['currencies', 'industries', 'languages', 'countries', 'banks'])) {
                    $orderBy = 'name';
                } else {
                    $orderBy = 'id';
                }
                $tableData = $class::orderBy($orderBy)->get();
                if ($tableData->count()) {
                    Cache::forever($name, $tableData);
                }
            }
        }



        $this->account = factory(\App\Models\Account::class)->create();
        $this->company = factory(\App\Models\Company::class)->create([
            'account_id' => $this->account->id,
        ]);

        $this->account->default_company_id = $this->company->id;
        $this->account->save();

        $this->user = User::whereEmail('user@example.com')->first();

        if(!$this->user){
            $this->user = factory(\App\Models\User::class)->create([
                'password' => 'ALongAndBriliantPassword',
                'confirmation_code' => $this->createDbHash(config('database.default'))
            ]);
        }
        
        $cu = CompanyUserFactory::create($this->user->id, $this->company->id, $this->account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->save();

        $this->token = \Illuminate\Support\Str::random(64);

        $company_token = CompanyToken::create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'account_id' => $this->account->id,
            'name' => 'test token',
            'token' => $this->token,
        ]);

        // $this->user->companies()->attach($this->company->id, [
        //     'account_id' => $this->account->id,
        //     'is_owner' => 1,
        //     'is_admin' => 1,
        //     'is_locked' => 0,
        //     'permissions' => '',
        //     'settings' => json_encode(DefaultSettings::userSettings()),
        // ]);

         $this->client = ClientFactory::create($this->company->id, $this->user->id);
         $this->client->save();

            factory(\App\Models\ClientContact::class,1)->create([
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
                'company_id' => $this->company->id,
                'is_primary' => 1,
                'send_invoice' => true,
            ]);

            factory(\App\Models\ClientContact::class,1)->create([
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
                'company_id' => $this->company->id,
                'send_invoice' => true
            ]);

        
        $gs = new GroupSetting;
        $gs->name = 'Test';
        $gs->company_id = $this->client->company_id;
        $gs->settings = ClientSettings::buildClientSettings($this->company->settings, $this->client->settings);

        $gs_settings = $gs->settings;
        $gs_settings->website = 'http://staging.invoicing.co';
        $gs->settings = $gs_settings;
        $gs->save();

        $this->client->group_settings_id = $gs->id;
        $this->client->save();
 
        $this->invoice = InvoiceFactory::create($this->company->id,$this->user->id);//stub the company and user_id
        $this->invoice->client_id = $this->client->id;

		$this->invoice->line_items = $this->buildLineItems();
		$this->invoice->uses_inclusive_Taxes = false;

        $this->invoice->save();

		$this->invoice_calc = new InvoiceSum($this->invoice);
		$this->invoice_calc->build();

		$this->invoice = $this->invoice_calc->getInvoice();

        $this->invoice->save();

        $this->invoice->markSent();
        
        $contacts = $this->invoice->client->contacts;

        $contacts->each(function ($contact) {

            $invitation = InvoiceInvitation::whereCompanyId($this->invoice->company_id)
                                        ->whereClientContactId($contact->id)
                                        ->whereInvoiceId($this->invoice->id)
                                        ->first();

            if(!$invitation && $contact->send_invoice) {
                $ii = InvoiceInvitationFactory::create($this->invoice->company_id, $this->invoice->user_id);
                $ii->invoice_id = $this->invoice->id;
                $ii->client_contact_id = $contact->id;
                $ii->save();
            }
            else if($invitation && !$contact->send_invoice) {
                $invitation->delete();
            }

        });

        UpdateCompanyLedgerWithInvoice::dispatchNow($this->invoice, $this->invoice->amount, $this->invoice->company);

        $recurring_invoice = InvoiceToRecurringInvoiceFactory::create($this->invoice);
        $recurring_invoice->next_send_date = Carbon::now();
        $recurring_invoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        $recurring_invoice->remaining_cycles = 2;
        $recurring_invoice->start_date = Carbon::now();
        $recurring_invoice->save();
        
        $recurring_invoice->number = $this->getNextInvoiceNumber($this->invoice->client);
        $recurring_invoice->save();

        $recurring_invoice = InvoiceToRecurringInvoiceFactory::create($this->invoice);
        $recurring_invoice->next_send_date = Carbon::now()->addMinutes(2);
        $recurring_invoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        $recurring_invoice->remaining_cycles = 2;
        $recurring_invoice->start_date = Carbon::now();
        $recurring_invoice->save();
        
        $recurring_invoice->number = $this->getNextInvoiceNumber($this->invoice->client);
        $recurring_invoice->save();

        $recurring_invoice = InvoiceToRecurringInvoiceFactory::create($this->invoice);
        $recurring_invoice->next_send_date = Carbon::now()->addMinutes(10);
        $recurring_invoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        $recurring_invoice->remaining_cycles = 2;
        $recurring_invoice->start_date = Carbon::now();
        $recurring_invoice->save();
        
        $recurring_invoice->number = $this->getNextInvoiceNumber($this->invoice->client);
        $recurring_invoice->save();

        $recurring_invoice = InvoiceToRecurringInvoiceFactory::create($this->invoice);
        $recurring_invoice->next_send_date = Carbon::now()->addMinutes(15);
        $recurring_invoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        $recurring_invoice->remaining_cycles = 2;
        $recurring_invoice->start_date = Carbon::now();
        $recurring_invoice->save();
        
        $recurring_invoice->number = $this->getNextInvoiceNumber($this->invoice->client);
        $recurring_invoice->save();


        $recurring_invoice = InvoiceToRecurringInvoiceFactory::create($this->invoice);
        $recurring_invoice->next_send_date = Carbon::now()->addMinutes(20);
        $recurring_invoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        $recurring_invoice->remaining_cycles = 2;
        $recurring_invoice->start_date = Carbon::now();
        $recurring_invoice->save();
        
        $recurring_invoice->number = $this->getNextInvoiceNumber($this->invoice->client);
        $recurring_invoice->save();

        $recurring_invoice = InvoiceToRecurringInvoiceFactory::create($this->invoice);
        $recurring_invoice->next_send_date = Carbon::now()->addDays(10);
        $recurring_invoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        $recurring_invoice->remaining_cycles = 2;
        $recurring_invoice->start_date = Carbon::now();
        $recurring_invoice->save();
        
        $recurring_invoice->number = $this->getNextInvoiceNumber($this->invoice->client);
        $recurring_invoice->save();

        $gs = new GroupSetting;
        $gs->company_id = $this->company->id;
        $gs->user_id = $this->user->id;
        $gs->settings = ClientSettings::buildClientSettings(CompanySettings::defaults(), ClientSettings::defaults());
        $gs->name = 'Default Client Settings';
        $gs->save();

        if(config('ninja.testvars.stripe'))
        {

            $cg = new CompanyGateway;
            $cg->company_id = $this->company->id;
            $cg->user_id = $this->user->id;
            $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
            $cg->require_cvv = true;
            $cg->show_billing_address = true;
            $cg->show_shipping_address = true;
            $cg->update_details = true;
            $cg->config = encrypt(config('ninja.testvars.stripe'));
            $cg->save();


            $cg = new CompanyGateway;
            $cg->company_id = $this->company->id;
            $cg->user_id = $this->user->id;
            $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
            $cg->require_cvv = true;
            $cg->show_billing_address = true;
            $cg->show_shipping_address = true;
            $cg->update_details = true;
            $cg->config = encrypt(config('ninja.testvars.stripe'));
            $cg->save();
        }

	}


	private function buildLineItems()
	{
		$line_items = [];

		$item = InvoiceItemFactory::create();
		$item->quantity = 1;
		$item->cost =10;

		$line_items[] = $item;

		return $line_items;

	}
}