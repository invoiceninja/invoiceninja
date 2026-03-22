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

namespace Tests\Feature\Bank;

use Tests\TestCase;
use App\Models\Payment;
use Tests\MockAccountData;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Models\BankTransactionRule;
use App\Models\Invoice;
use App\Services\Bank\ProcessBankRules;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Str;

/**
 * Test credit transaction rule chaining - multiple rules with AND/OR logic
 */
class CreditRuleChainingTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testMultipleRulesMatchAny()
    {
        // Test "Match ANY" - should match if ANY condition passes

        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $hash = Str::random(32);
        $rand_amount = rand(1000, 10000000);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => $hash,
            'base_type' => 'CREDIT',
            'amount' => $rand_amount
        ]);

        // Create rule with TWO conditions, only ONE will match
        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,  // Match ANY
            'auto_convert' => false,
            'applies_to' => 'CREDIT',
            'rules' => [
                [
                    'search_key' => '$invoice.number',
                    'operator' => 'contains',
                ],
                [
                    'search_key' => '$invoice.amount',
                    'operator' => '=',
                ]
            ]
        ]);

        // Create invoice that matches amount but NOT description
        $i = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'amount' => $rand_amount,
            'balance' => $rand_amount,
            'number' => 'DIFFERENT-NUMBER',  // Won't match description
            'status_id' => 2,
        ]);

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);

        (new ProcessBankRules($bt))->run();

        $bt = $bt->fresh();

        // Should match because amount matched (Match ANY)
        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertNotNull($bt->invoice_ids);
        $this->assertEquals($i->hashed_id, $bt->invoice_ids);
    }

    public function testMultipleRulesMatchAll()
    {
        // Test "Match ALL" - should match only if ALL conditions pass

        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $hash = Str::random(32);
        $rand_amount = rand(1000, 10000000);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => $hash,
            'base_type' => 'CREDIT',
            'amount' => $rand_amount
        ]);

        // Create rule with TWO conditions, BOTH must match
        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => true,  // Match ALL
            'auto_convert' => false,
            'applies_to' => 'CREDIT',
            'rules' => [
                [
                    'search_key' => '$invoice.number',
                    'operator' => 'contains',
                ],
                [
                    'search_key' => '$invoice.amount',
                    'operator' => '=',
                ]
            ]
        ]);

        // Create invoice that matches BOTH conditions
        $i = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'amount' => $rand_amount,
            'balance' => $rand_amount,
            'number' => $hash,  // Matches description
            'status_id' => 2,
        ]);

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);

        (new ProcessBankRules($bt))->run();

        $bt = $bt->fresh();

        // Should match because BOTH conditions matched
        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertNotNull($bt->invoice_ids);
        $this->assertEquals($i->hashed_id, $bt->invoice_ids);
    }

    public function testMultipleRulesMatchAllFails()
    {
        // Test "Match ALL" - should NOT match if only some conditions pass

        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $hash = Str::random(32);
        $rand_amount = rand(1000, 10000000);
        $different_amount = rand(10000001, 20000000); // Ensure different from rand_amount

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => $hash,
            'base_type' => 'CREDIT',
            'amount' => $rand_amount
        ]);

        // Create rule with TWO conditions, BOTH must match
        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => true,  // Match ALL
            'auto_convert' => false,
            'applies_to' => 'CREDIT',
            'rules' => [
                [
                    'search_key' => '$invoice.number',
                    'operator' => 'contains',
                ],
                [
                    'search_key' => '$invoice.amount',
                    'operator' => '=',
                ]
            ]
        ]);

        // Create invoice that matches NEITHER condition
        // - Different invoice number (won't match description)
        // - Different amount (won't match transaction amount)
        $i = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'amount' => $different_amount,  // Different amount
            'balance' => $different_amount,
            'number' => 'INV-NOMATCH-' . Str::random(10),  // Won't match hash
            'status_id' => 2,
        ]);

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);

        (new ProcessBankRules($bt))->run();

        $bt = $bt->fresh();

        // Should NOT match because NEITHER condition matched
        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);
        $this->assertTrue($bt->invoice_ids === null || $bt->invoice_ids === '');
        $this->assertNull($bt->payment_id);
    }

    public function testThreeRulesMatchAll()
    {
        // Test THREE conditions with "Match ALL"

        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $hash = Str::random(32);
        $rand_amount = rand(1000, 10000000);
        $custom_value = Str::random(16);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => $hash . ' ' . $custom_value,
            'base_type' => 'CREDIT',
            'amount' => $rand_amount
        ]);

        // Create rule with THREE conditions
        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => true,  // Match ALL
            'auto_convert' => false,
            'applies_to' => 'CREDIT',
            'rules' => [
                [
                    'search_key' => '$invoice.number',
                    'operator' => 'contains',
                ],
                [
                    'search_key' => '$invoice.amount',
                    'operator' => '=',
                ],
                [
                    'search_key' => '$invoice.custom1',
                    'operator' => 'contains',
                ]
            ]
        ]);

        // Create invoice that matches ALL THREE conditions
        $i = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'amount' => $rand_amount,
            'balance' => $rand_amount,
            'number' => $hash,
            'custom_value1' => $custom_value,
            'status_id' => 2,
        ]);

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);

        (new ProcessBankRules($bt))->run();

        $bt = $bt->fresh();

        // Should match because ALL THREE conditions matched
        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertNotNull($bt->invoice_ids);
        $this->assertEquals($i->hashed_id, $bt->invoice_ids);
    }

    public function testInvoiceAndClientMatching()
    {
        // Test matching invoice with client constraint

        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $rand_amount = rand(1000, 10000000);
        $client_id = Str::random(16);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => $client_id,
            'base_type' => 'CREDIT',
            'amount' => $rand_amount
        ]);

        // Create rule matching amount AND client ID
        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => true,  // Match ALL
            'auto_convert' => false,
            'applies_to' => 'CREDIT',
            'rules' => [
                [
                    'search_key' => '$invoice.amount',
                    'operator' => '=',
                ],
                [
                    'search_key' => '$client.id_number',
                    'operator' => 'is',
                ]
            ]
        ]);

        // Update client with ID number
        $this->client->id_number = $client_id;
        $this->client->save();

        // Create invoice for this client with matching amount
        $i = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'amount' => $rand_amount,
            'balance' => $rand_amount,
            'status_id' => 2,
        ]);

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);

        (new ProcessBankRules($bt))->run();

        $bt = $bt->fresh();

        // Should match via client ID intersection
        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertNotNull($bt->invoice_ids);
        $this->assertEquals($i->hashed_id, $bt->invoice_ids);
    }

    public function testPaymentAndClientMatching()
    {
        // Test matching payment with client constraint

        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $rand_amount = rand(1000, 10000000);
        $client_id = Str::random(16);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => $client_id,
            'base_type' => 'CREDIT',
            'amount' => $rand_amount
        ]);

        // Create rule matching payment amount AND client ID
        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => true,  // Match ALL
            'auto_convert' => false,
            'applies_to' => 'CREDIT',
            'rules' => [
                [
                    'search_key' => '$payment.amount',
                    'operator' => '=',
                ],
                [
                    'search_key' => '$client.id_number',
                    'operator' => 'is',
                ]
            ]
        ]);

        // Update client with ID number
        $this->client->id_number = $client_id;
        $this->client->save();

        // Create payment for this client with matching amount
        $p = Payment::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'amount' => $rand_amount,
        ]);

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);

        (new ProcessBankRules($bt))->run();

        $bt = $bt->fresh();

        // Should match via client ID intersection
        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertNotNull($bt->payment_id);
        $this->assertEquals($p->id, $bt->payment_id);
    }

    public function testCustomFieldMatching()
    {
        // Test matching on invoice custom fields

        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $custom1 = Str::random(16);
        $custom2 = Str::random(16);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => $custom1 . ' ' . $custom2,
            'base_type' => 'CREDIT',
            'amount' => 1000
        ]);

        // Create rule matching TWO custom fields
        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => true,  // Match ALL
            'auto_convert' => false,
            'applies_to' => 'CREDIT',
            'rules' => [
                [
                    'search_key' => '$invoice.custom1',
                    'operator' => 'contains',
                ],
                [
                    'search_key' => '$invoice.custom2',
                    'operator' => 'contains',
                ]
            ]
        ]);

        // Create invoice with matching custom fields
        $i = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'amount' => 1000,
            'balance' => 1000,
            'custom_value1' => $custom1,
            'custom_value2' => $custom2,
            'status_id' => 2,
        ]);

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);

        (new ProcessBankRules($bt))->run();

        $bt = $bt->fresh();

        // Should match on custom fields
        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertNotNull($bt->invoice_ids);
        $this->assertEquals($i->hashed_id, $bt->invoice_ids);
    }

    public function testPaymentCustomFieldMatching()
    {
        // Test matching on payment custom fields

        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $custom1 = Str::random(16);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => $custom1,
            'base_type' => 'CREDIT',
            'amount' => 1000
        ]);

        // Create rule matching payment custom field
        $br = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => false,
            'applies_to' => 'CREDIT',
            'rules' => [
                [
                    'search_key' => '$payment.custom1',
                    'operator' => 'contains',
                ]
            ]
        ]);

        // Create payment with matching custom field
        $p = Payment::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'amount' => 1000,
            'custom_value1' => $custom1,
        ]);

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);

        (new ProcessBankRules($bt))->run();

        $bt = $bt->fresh();

        // Should match on payment custom field
        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertNotNull($bt->payment_id);
        $this->assertEquals($p->id, $bt->payment_id);
    }

    public function testFirstRuleWins()
    {
        // Test that first matching rule wins and stops processing

        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $custom1 = Str::random(16);
        $custom2 = Str::random(16);
        $rand_amount = rand(1000, 10000000);

        $bt = BankTransaction::factory()->create([
            'bank_integration_id' => $bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'description' => $custom1 . ' ' . $custom2,
            'base_type' => 'CREDIT',
            'amount' => $rand_amount
        ]);

        // Create FIRST rule that will match on custom1
        $br1 = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => false,
            'applies_to' => 'CREDIT',
            'rules' => [
                [
                    'search_key' => '$invoice.custom1',
                    'operator' => 'contains',
                ]
            ]
        ]);

        // Create SECOND rule that would also match on custom2
        $br2 = BankTransactionRule::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => false,
            'applies_to' => 'CREDIT',
            'rules' => [
                [
                    'search_key' => '$invoice.custom2',
                    'operator' => 'contains',
                ]
            ]
        ]);

        // Create invoice that matches BOTH rules
        $i = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'amount' => $rand_amount,
            'balance' => $rand_amount,
            'number' => 'INV-' . Str::random(10), // Different from description to avoid simple match
            'custom_value1' => $custom1,
            'custom_value2' => $custom2,
            'status_id' => 2,
        ]);

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);

        (new ProcessBankRules($bt))->run();

        $bt = $bt->fresh();

        // Should match with FIRST rule, not second
        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($br1->id, $bt->bank_transaction_rule_id);
        $this->assertNotEquals($br2->id, $bt->bank_transaction_rule_id);
    }
}
