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

namespace Tests\Feature\Bank;

use App\Factory\BankIntegrationFactory;
use App\Factory\BankTransactionFactory;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Models\BankTransactionRule;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class BankTransactionRuleTest extends TestCase
{

    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testMatchingBankTransactionExpense()
    {
        // $this->expense->public_notes = "WaLLaBy";
        // $this->expense->save();

        // $this->assertEquals('WaLLaBy', $this->expense->public_notes);

        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => true,
            'applies_to' => 'DEBIT',
            'client_id' => $this->client->id,
            'vendor_id' => $this->vendor->id,
            'rules' => [
                [
                    'search_key' => 'description',
                    'operator' => 'is',
                    'value' => 'wallaby',
                ]
            ]
        ]);

        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => 'WallABy',
            'base_type' => 'DEBIT',
        ]);
    

        $bt->service()->processRules();

        $bt = $bt->fresh();

        $this->assertNotNull($bt->expense_id);
    }

}