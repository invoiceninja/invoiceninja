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

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Account;
use App\Models\Company;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Currency;
use Tests\MockAccountData;
use Illuminate\Support\Str;
use App\Models\CompanyToken;
use App\DataMapper\InvoiceItem;
use App\Factory\InvoiceFactory;
use App\DataMapper\CompanySettings;
use App\Factory\CompanyUserFactory;
use App\Factory\InvoiceItemFactory;
use App\Helpers\Invoice\InvoiceSum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use App\Repositories\InvoiceRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use App\Helpers\Invoice\InvoiceSumInclusive;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InvoiceMarkPaidTest extends TestCase
{

    public $invoice;

    public $company;

    public $user;

    public $payload;

    public $account;

    public $client;

    public $token;

    public $cu;

    public function setUp(): void
    {
        parent::setUp();

        config(['database.default' => config('ninja.db.default')]);

        if (Country::count() == 0) {
            Artisan::call('db:seed', ['--force' => true]);
        }

        app()->singleton('currencies', function ($app) {

            $resource = Currency::query()->orderBy('name')->get();
            Cache::forever('currencies', $resource);
            return $resource;

        });
        
        Event::fake();

    }

    private function buildData()
    {

        if($this->account){
            $this->account->forceDelete();
        }
        /** @var \App\Models\Account $account */
        $this->account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $this->account->num_users = 3;
        $this->account->save();

        $this->user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => 'xyz123',
            'email' => \Illuminate\Support\Str::random(32)."@example.com",
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

    }

    public function testInvoiceMarkPaidFromDraft()
    {

        $this->buildData();

        $c = \App\Models\Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_name1 = '';
        $item->tax_rate1 = 0;
        $item->type_id = '1';
        $item->tax_id = '1';
        $line_items[] = $item;


        $i = Invoice::factory()->create([
            'discount' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $c->id,
            'line_items' => $line_items,
            'status_id' => 1,
            'uses_inclusive_taxes' => false,
            'is_amount_discount' => false
        ]);

        $i->calc()->getInvoice();

        $repo = new InvoiceRepository();
        $repo->save([], $i);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/invoices/{$i->hashed_id}?paid=true", []);

        $response->assertStatus(200);

        $this->assertEquals(0, $response->json('data.balance'));
        $this->assertEquals(10, $response->json('data.paid_to_date'));
        $this->assertEquals(4, $response->json('data.status_id'));

        $i = $i->fresh();

        $this->assertEquals(0, $i->balance);
        $this->assertEquals(10, $i->paid_to_date);
        $this->assertEquals(4, $i->status_id);

        $this->account->forceDelete();

    }


    public function testInvoiceMarkPaidFromDraftBulk()
    {

        $this->buildData();

        $c = \App\Models\Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_name1 = '';
        $item->tax_rate1 = 0;
        $item->type_id = '1';
        $item->tax_id = '1';
        $line_items[] = $item;


        /** @var \App\Models\Invoice $i */
        $i = Invoice::factory()->create([
            'discount' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $c->id,
            'line_items' => $line_items,
            'status_id' => 1,
            'uses_inclusive_taxes' => false,
            'is_amount_discount' => false
        ]);

        $i->calc()->getInvoice();

        $repo = new InvoiceRepository();
        $repo->save([], $i);

        $data = [
            'action' => 'mark_paid',
            'ids' => [$i->hashed_id]
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/invoices/bulk", $data);

        $response->assertStatus(200);

        $this->assertEquals(0, $response->json('data.0.balance'));
        $this->assertEquals(10, $response->json('data.0.paid_to_date'));
        $this->assertEquals(4, $response->json('data.0.status_id'));

        $i = $i->fresh();

        $this->assertEquals(0, $i->balance);
        $this->assertEquals(10, $i->paid_to_date);
        $this->assertEquals(4, $i->status_id);

        $this->account->forceDelete();

    }

}
