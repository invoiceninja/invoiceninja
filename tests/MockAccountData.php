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

use App\Factory\ClientFactory;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Helpers\Invoice\InvoiceCalc;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Class MockAccountData
 * @package Tests\Unit
 */
trait MockAccountData
{

	use MakesHash;
	
	public $account;

	public $company;

	public $user;

	public $client;



	public function makeTestData()
	{
        $this->account = factory(\App\Models\Account::class)->create();
        $this->company = factory(\App\Models\Company::class)->create([
            'account_id' => $this->account->id,
        ]);

        $this->account->default_company_id = $this->company->id;
        $this->account->save();

        $this->user = factory(\App\Models\User::class)->create([
        //    'account_id' => $account->id,
            'confirmation_code' => $this->createDbHash(config('database.default'))
        ]);

        $this->client = ClientFactory::create($this->company->id, $this->user->id);
        $this->client->save();

        $this->invoice = InvoiceFactory::create($this->company->id,$this->user->id);//stub the company and user_id
        $this->invoice->client_id = $this->client->id;

		$this->invoice->line_items = $this->buildLineItems();
		
		$this->settings = $this->client->settings;

		$this->settings->custom_taxes1 = false;
		$this->settings->custom_taxes2 = false;
		$this->settings->inclusive_taxes = false;
		$this->settings->precision = 2;

		$this->invoice->settings = $this->settings;

		$this->invoice_calc = new InvoiceCalc($this->invoice, $this->settings);
		$this->invoice_calc->build();

		$this->invoice = $this->invoice_calc->getInvoice();

        $this->invoice->save();

	}


	private function buildLineItems()
	{
		$line_items = [];

		$item = InvoiceItemFactory::create();
		$item->qty = 1;
		$item->cost =10;

		$line_items[] = $item;

		return $line_items;

	}
}