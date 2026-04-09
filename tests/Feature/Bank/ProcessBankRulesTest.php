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
use App\Models\Client;
use App\Models\Vendor;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Expense;
use Tests\MockAccountData;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Models\BankTransactionRule;
use App\Services\Bank\ProcessBankRules;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;

class ProcessBankRulesTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private BankIntegration $bi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(ThrottleRequests::class);

        $this->bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);
    }

    private function createBankTransaction(array $overrides = []): BankTransaction
    {
        return BankTransaction::factory()->create(array_merge([
            'bank_integration_id' => $this->bi->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'base_type' => 'CREDIT',
            'status_id' => BankTransaction::STATUS_UNMATCHED,
        ], $overrides));
    }

    private function createRule(array $rules, array $overrides = []): BankTransactionRule
    {
        return BankTransactionRule::factory()->create(array_merge([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'matches_on_all' => false,
            'auto_convert' => false,
            'applies_to' => 'CREDIT',
            'rules' => $rules,
        ], $overrides));
    }

    private function createInvoice(array $overrides = []): Invoice
    {
        return Invoice::factory()->create(array_merge([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'status_id' => 2,
            'is_deleted' => false,
        ], $overrides));
    }

    private function createPayment(array $overrides = []): Payment
    {
        return Payment::factory()->create(array_merge([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'status_id' => 4,
            'is_deleted' => false,
            'transaction_id' => null,
        ], $overrides));
    }

    // ── $invoice.number ──────────────────────────────────────────────

    public function testInvoiceNumberOperatorIs()
    {
        // Word-boundary match is case-insensitive and works when the
        // description is exactly the invoice number (different case)
        $number = 'INV-' . Str::random(20);

        $bt = $this->createBankTransaction([
            'description' => strtolower($number),
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'is', 'value' => '$invoice.number'],
        ]);

        $invoice = $this->createInvoice([
            'number' => $number,
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($invoice->hashed_id, $bt->invoice_ids);
    }

    public function testInvoiceNumberOperatorContains()
    {
        $number = 'INV-' . Str::random(20);

        $bt = $this->createBankTransaction([
            'description' => 'payment for ' . strtolower($number) . ' received',
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$invoice.number'],
        ]);

        $invoice = $this->createInvoice([
            'number' => $number,
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($invoice->hashed_id, $bt->invoice_ids);
    }

    public function testInvoiceNumberOperatorStartsWith()
    {
        $number = 'INV-' . Str::random(20);

        $bt = $this->createBankTransaction([
            'description' => strtolower($number) . ' extra text',
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'starts_with', 'value' => '$invoice.number'],
        ]);

        $invoice = $this->createInvoice([
            'number' => $number,
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($invoice->hashed_id, $bt->invoice_ids);
    }

    public function testInvoiceNumberMatchesWithSurroundingText()
    {
        $number = 'INV-' . Str::random(20);

        // Word-boundary match finds the invoice number even with surrounding text
        $bt = $this->createBankTransaction([
            'description' => 'payment for ' . strtolower($number),
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'is', 'value' => '$invoice.number'],
        ]);

        $invoice = $this->createInvoice([
            'number' => $number,
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($invoice->hashed_id, $bt->invoice_ids);
    }

    // ── $invoice.number edge cases ─────────────────────────────────

    public function testInvoiceNumberSubstringDoesNotFalseMatch()
    {
        // INV-001 must NOT match when the description contains INV-0010
        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$invoice.number'],
        ]);

        $this->createInvoice([
            'number' => 'INV-001',
            'amount' => 50,
            'balance' => 50,
        ]);

        $longInvoice = $this->createInvoice([
            'number' => 'INV-0010',
            'amount' => 100,
            'balance' => 100,
        ]);

        $bt = $this->createBankTransaction([
            'description' => 'Payment for INV-0010',
            'amount' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($longInvoice->hashed_id, $bt->invoice_ids);
    }

    public function testInvoiceNumberPrefixSubstringDoesNotFalseMatch()
    {
        // INV-10 must NOT match when description contains INV-100
        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$invoice.number'],
        ]);

        $this->createInvoice([
            'number' => 'INV-10',
            'amount' => 50,
            'balance' => 50,
        ]);

        $correctInvoice = $this->createInvoice([
            'number' => 'INV-100',
            'amount' => 100,
            'balance' => 100,
        ]);

        $bt = $this->createBankTransaction([
            'description' => 'Ref INV-100 thank you',
            'amount' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($correctInvoice->hashed_id, $bt->invoice_ids);
    }

    public function testInvoiceNumberNumericOnlySubstring()
    {
        // Pure numeric invoice "001" must NOT match description "10010"
        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$invoice.number'],
        ]);

        $this->createInvoice([
            'number' => '001',
            'amount' => 50,
            'balance' => 50,
        ]);

        $bt = $this->createBankTransaction([
            'description' => 'Transfer ref 10010',
            'amount' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);
    }

    public function testInvoiceNumberAtStartOfDescription()
    {
        $number = 'INV-' . Str::random(10);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$invoice.number'],
        ]);

        $invoice = $this->createInvoice([
            'number' => $number,
            'amount' => 100,
            'balance' => 100,
        ]);

        $bt = $this->createBankTransaction([
            'description' => $number . ' payment received',
            'amount' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($invoice->hashed_id, $bt->invoice_ids);
    }

    public function testInvoiceNumberAtEndOfDescription()
    {
        $number = 'INV-' . Str::random(10);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$invoice.number'],
        ]);

        $invoice = $this->createInvoice([
            'number' => $number,
            'amount' => 100,
            'balance' => 100,
        ]);

        $bt = $this->createBankTransaction([
            'description' => 'Payment received for ' . $number,
            'amount' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($invoice->hashed_id, $bt->invoice_ids);
    }

    public function testInvoiceNumberCaseInsensitiveMatch()
    {
        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$invoice.number'],
        ]);

        $invoice = $this->createInvoice([
            'number' => 'INV-ABC123',
            'amount' => 100,
            'balance' => 100,
        ]);

        $bt = $this->createBankTransaction([
            'description' => 'payment inv-abc123 done',
            'amount' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($invoice->hashed_id, $bt->invoice_ids);
    }

    public function testInvoiceNumberNoMatchWhenAbsentFromDescription()
    {
        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$invoice.number'],
        ]);

        $this->createInvoice([
            'number' => 'INV-999',
            'amount' => 100,
            'balance' => 100,
        ]);

        $bt = $this->createBankTransaction([
            'description' => 'General deposit from client',
            'amount' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);
    }

    public function testInvoiceNumberWithNewlineInDescription()
    {
        $number = 'INV-' . Str::random(10);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$invoice.number'],
        ]);

        $invoice = $this->createInvoice([
            'number' => $number,
            'amount' => 100,
            'balance' => 100,
        ]);

        $bt = $this->createBankTransaction([
            'description' => "Payment\nfor " . $number . "\nreceived",
            'amount' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($invoice->hashed_id, $bt->invoice_ids);
    }

    public function testInvoiceNumberSplitAcrossNewlineDoesNotMatch()
    {
        // Invoice number split by a newline should not match
        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$invoice.number'],
        ]);

        $this->createInvoice([
            'number' => 'INV-12345',
            'amount' => 100,
            'balance' => 100,
        ]);

        $bt = $this->createBankTransaction([
            'description' => "Reference INV-123\n45 paid",
            'amount' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);
    }

    public function testInvoiceNumberWithSpecialRegexChars()
    {
        // Ensure invoice numbers containing regex metacharacters are handled safely
        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$invoice.number'],
        ]);

        $invoice = $this->createInvoice([
            'number' => 'INV(2026).001',
            'amount' => 100,
            'balance' => 100,
        ]);

        $bt = $this->createBankTransaction([
            'description' => 'Payment for INV(2026).001 received',
            'amount' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($invoice->hashed_id, $bt->invoice_ids);
    }

    public function testInvoiceNumberDeletedInvoiceIsExcluded()
    {
        $number = 'INV-' . Str::random(10);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$invoice.number'],
        ]);

        // Soft-deleted AND is_deleted = true should be excluded
        $this->createInvoice([
            'number' => $number,
            'amount' => 100,
            'balance' => 100,
            'is_deleted' => true,
        ]);

        $bt = $this->createBankTransaction([
            'description' => 'Payment ' . $number,
            'amount' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);
    }

    public function testInvoiceNumberPaidInvoiceIsExcluded()
    {
        $number = 'INV-' . Str::random(10);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$invoice.number'],
        ]);

        // status_id 4 = paid — should not match
        $this->createInvoice([
            'number' => $number,
            'amount' => 100,
            'balance' => 0,
            'status_id' => 4,
        ]);

        $bt = $this->createBankTransaction([
            'description' => 'Payment ' . $number,
            'amount' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);
    }

    public function testInvoiceNumberSingleCharNumberIsIgnored()
    {
        // Invoice numbers shorter than 2 characters should be skipped
        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$invoice.number'],
        ]);

        $this->createInvoice([
            'number' => '1',
            'amount' => 100,
            'balance' => 100,
        ]);

        $bt = $this->createBankTransaction([
            'description' => 'Payment reference 1 received',
            'amount' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);
    }

    public function testInvoiceNumberLongestMatchWins()
    {
        // When multiple invoices match, the longest number should be chosen
        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$invoice.number'],
        ]);

        // Both "R-50" and "R-500" exist; description has both as valid tokens
        $this->createInvoice([
            'number' => 'R-50',
            'amount' => 50,
            'balance' => 50,
        ]);

        $longerInvoice = $this->createInvoice([
            'number' => 'R-500',
            'amount' => 500,
            'balance' => 500,
        ]);

        $bt = $this->createBankTransaction([
            'description' => 'Payment for R-50 and R-500',
            'amount' => 500,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($longerInvoice->hashed_id, $bt->invoice_ids);
    }

    // ── $invoice.amount ──────────────────────────────────────────────

    public function testInvoiceAmountOperatorEquals()
    {
        $amount = 12345.67;

        $bt = $this->createBankTransaction([
            'description' => Str::random(32),
            'amount' => $amount,
        ]);

        $this->createRule([
            ['search_key' => 'amount', 'operator' => '=', 'value' => '$invoice.amount'],
        ]);

        $invoice = $this->createInvoice([
            'amount' => $amount,
            'balance' => $amount,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($invoice->hashed_id, $bt->invoice_ids);
    }

    public function testInvoiceAmountOperatorGreaterThan()
    {
        $bt = $this->createBankTransaction([
            'description' => Str::random(32),
            'amount' => 200,
        ]);

        $this->createRule([
            ['search_key' => 'amount', 'operator' => '>', 'value' => '$invoice.amount'],
        ]);

        // invoice.amount = 100, bt.amount = 200 => 200 > 100 = true
        $this->createInvoice([
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertNotNull($bt->invoice_ids);
    }

    public function testInvoiceAmountOperatorGreaterThanFails()
    {
        // Use a tiny amount so bt.amount > any_invoice.amount is false
        $bt = $this->createBankTransaction([
            'description' => Str::random(32),
            'amount' => 0.01,
        ]);

        $this->createRule([
            ['search_key' => 'amount', 'operator' => '>', 'value' => '$invoice.amount'],
        ]);

        // invoice.amount = 100, bt.amount = 0.01 => 0.01 > 100 = false
        $this->createInvoice([
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);
    }

    public function testInvoiceAmountOperatorLessThan()
    {
        $bt = $this->createBankTransaction([
            'description' => Str::random(32),
            'amount' => 50,
        ]);

        $this->createRule([
            ['search_key' => 'amount', 'operator' => '<', 'value' => '$invoice.amount'],
        ]);

        // invoice.amount = 100, bt.amount = 50 => 50 < 100 = true
        $invoice = $this->createInvoice([
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
    }

    public function testInvoiceAmountOperatorLessThanOrEqual()
    {
        $amount = 100;

        $bt = $this->createBankTransaction([
            'description' => Str::random(32),
            'amount' => $amount,
        ]);

        $this->createRule([
            ['search_key' => 'amount', 'operator' => '<=', 'value' => '$invoice.amount'],
        ]);

        $invoice = $this->createInvoice([
            'amount' => $amount,
            'balance' => $amount,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
    }

    public function testInvoiceAmountOperatorGreaterThanOrEqual()
    {
        $amount = 100;

        $bt = $this->createBankTransaction([
            'description' => Str::random(32),
            'amount' => $amount,
        ]);

        $this->createRule([
            ['search_key' => 'amount', 'operator' => '>=', 'value' => '$invoice.amount'],
        ]);

        $invoice = $this->createInvoice([
            'amount' => $amount,
            'balance' => $amount,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
    }

    // ── $invoice.po_number ───────────────────────────────────────────

    public function testInvoicePONumberContains()
    {
        $po = 'PO-' . Str::random(16);

        $bt = $this->createBankTransaction([
            'description' => 'Ref: ' . $po . ' paid',
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$invoice.po_number'],
        ]);

        $invoice = $this->createInvoice([
            'po_number' => $po,
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($invoice->hashed_id, $bt->invoice_ids);
    }

    public function testInvoicePONumberIs()
    {
        $po = 'PO-' . Str::random(16);

        $bt = $this->createBankTransaction([
            'description' => $po,
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'is', 'value' => '$invoice.po_number'],
        ]);

        $invoice = $this->createInvoice([
            'po_number' => $po,
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($invoice->hashed_id, $bt->invoice_ids);
    }

    public function testInvoicePONumberStartsWith()
    {
        $po = 'PO-' . Str::random(16);

        $bt = $this->createBankTransaction([
            'description' => $po . ' additional info',
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'starts_with', 'value' => '$invoice.po_number'],
        ]);

        $invoice = $this->createInvoice([
            'po_number' => $po,
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($invoice->hashed_id, $bt->invoice_ids);
    }

    // ── $invoice.custom1-4 ───────────────────────────────────────────

    public function testInvoiceCustom1Contains()
    {
        $custom = Str::random(16);

        $bt = $this->createBankTransaction([
            'description' => 'prefix ' . $custom . ' suffix',
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$invoice.custom1'],
        ]);

        $invoice = $this->createInvoice([
            'custom_value1' => $custom,
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($invoice->hashed_id, $bt->invoice_ids);
    }

    public function testInvoiceCustom2Contains()
    {
        $custom = Str::random(16);

        $bt = $this->createBankTransaction([
            'description' => $custom,
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$invoice.custom2'],
        ]);

        $invoice = $this->createInvoice([
            'custom_value2' => $custom,
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($invoice->hashed_id, $bt->invoice_ids);
    }

    public function testInvoiceCustom3Contains()
    {
        $custom = Str::random(16);

        $bt = $this->createBankTransaction([
            'description' => $custom,
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$invoice.custom3'],
        ]);

        $invoice = $this->createInvoice([
            'custom_value3' => $custom,
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($invoice->hashed_id, $bt->invoice_ids);
    }

    public function testInvoiceCustom4Contains()
    {
        $custom = Str::random(16);

        $bt = $this->createBankTransaction([
            'description' => $custom,
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$invoice.custom4'],
        ]);

        $invoice = $this->createInvoice([
            'custom_value4' => $custom,
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($invoice->hashed_id, $bt->invoice_ids);
    }

    // ── $payment.amount ──────────────────────────────────────────────

    public function testPaymentAmountEquals()
    {
        $amount = 9876.54;

        $bt = $this->createBankTransaction([
            'description' => Str::random(32),
            'amount' => $amount,
        ]);

        $this->createRule([
            ['search_key' => 'amount', 'operator' => '=', 'value' => '$payment.amount'],
        ]);

        $payment = $this->createPayment([
            'amount' => $amount,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($payment->id, $bt->payment_id);
    }

    public function testPaymentAmountGreaterThan()
    {
        $bt = $this->createBankTransaction([
            'description' => Str::random(32),
            'amount' => 500,
        ]);

        $this->createRule([
            ['search_key' => 'amount', 'operator' => '>', 'value' => '$payment.amount'],
        ]);

        // bt.amount = 500, payment.amount = 100 => 500 > 100 = true
        $this->createPayment([
            'amount' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertNotNull($bt->payment_id);
    }

    // ── $payment.transaction_reference ───────────────────────────────

    public function testPaymentTransactionReferenceContains()
    {
        $ref = 'TXN-' . Str::random(16);

        $bt = $this->createBankTransaction([
            'description' => 'Payment ' . $ref . ' processed',
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$payment.transaction_reference'],
        ]);

        $payment = $this->createPayment([
            'amount' => 100,
            'transaction_reference' => $ref,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($payment->id, $bt->payment_id);
    }

    public function testPaymentTransactionReferenceIs()
    {
        $ref = 'TXN-' . Str::random(16);

        $bt = $this->createBankTransaction([
            'description' => $ref,
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'is', 'value' => '$payment.transaction_reference'],
        ]);

        $payment = $this->createPayment([
            'amount' => 100,
            'transaction_reference' => $ref,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($payment->id, $bt->payment_id);
    }

    public function testPaymentTransactionReferenceStartsWith()
    {
        $ref = 'TXN-' . Str::random(16);

        $bt = $this->createBankTransaction([
            'description' => $ref . ' extra',
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'starts_with', 'value' => '$payment.transaction_reference'],
        ]);

        $payment = $this->createPayment([
            'amount' => 100,
            'transaction_reference' => $ref,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($payment->id, $bt->payment_id);
    }

    // ── $payment.custom1-4 ───────────────────────────────────────────

    public function testPaymentCustom1Contains()
    {
        $custom = Str::random(16);

        $bt = $this->createBankTransaction([
            'description' => $custom,
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$payment.custom1'],
        ]);

        $payment = $this->createPayment([
            'amount' => 100,
            'custom_value1' => $custom,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($payment->id, $bt->payment_id);
    }

    public function testPaymentCustom2Contains()
    {
        $custom = Str::random(16);

        $bt = $this->createBankTransaction([
            'description' => $custom,
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$payment.custom2'],
        ]);

        $payment = $this->createPayment([
            'amount' => 100,
            'custom_value2' => $custom,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($payment->id, $bt->payment_id);
    }

    public function testPaymentCustom3Contains()
    {
        $custom = Str::random(16);

        $bt = $this->createBankTransaction([
            'description' => $custom,
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$payment.custom3'],
        ]);

        $payment = $this->createPayment([
            'amount' => 100,
            'custom_value3' => $custom,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($payment->id, $bt->payment_id);
    }

    public function testPaymentCustom4Contains()
    {
        $custom = Str::random(16);

        $bt = $this->createBankTransaction([
            'description' => $custom,
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$payment.custom4'],
        ]);

        $payment = $this->createPayment([
            'amount' => 100,
            'custom_value4' => $custom,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($payment->id, $bt->payment_id);
    }

    // ── $client.id_number ────────────────────────────────────────────

    public function testClientIdNumberIs()
    {
        $idNumber = 'CLI-' . Str::random(16);

        $bt = $this->createBankTransaction([
            'description' => $idNumber,
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'is', 'value' => '$client.id_number'],
            ['search_key' => 'amount', 'operator' => '=', 'value' => '$invoice.amount'],
        ], ['matches_on_all' => true]);

        $this->client->id_number = $idNumber;
        $this->client->save();

        $invoice = $this->createInvoice([
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($invoice->hashed_id, $bt->invoice_ids);
    }

    public function testClientIdNumberContains()
    {
        $idNumber = 'CLI-' . Str::random(16);

        $bt = $this->createBankTransaction([
            'description' => 'Customer ' . $idNumber . ' payment',
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$client.id_number'],
            ['search_key' => 'amount', 'operator' => '=', 'value' => '$invoice.amount'],
        ], ['matches_on_all' => true]);

        $this->client->id_number = $idNumber;
        $this->client->save();

        $invoice = $this->createInvoice([
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($invoice->hashed_id, $bt->invoice_ids);
    }

    // ── $client.email ────────────────────────────────────────────────

    public function testClientEmailContains()
    {
        $email = $this->client->contacts->first()->email;

        $bt = $this->createBankTransaction([
            'description' => 'From ' . $email . ' ref 123',
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$client.email'],
            ['search_key' => 'amount', 'operator' => '=', 'value' => '$invoice.amount'],
        ], ['matches_on_all' => true]);

        $invoice = $this->createInvoice([
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($invoice->hashed_id, $bt->invoice_ids);
    }

    public function testClientEmailIs()
    {
        $email = $this->client->contacts->first()->email;

        $bt = $this->createBankTransaction([
            'description' => $email,
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'is', 'value' => '$client.email'],
            ['search_key' => 'amount', 'operator' => '=', 'value' => '$invoice.amount'],
        ], ['matches_on_all' => true]);

        $invoice = $this->createInvoice([
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($invoice->hashed_id, $bt->invoice_ids);
    }

    // ── $client.custom1-4 ────────────────────────────────────────────

    public function testClientCustom1Contains()
    {
        $custom = Str::random(16);

        $this->client->custom_value1 = $custom;
        $this->client->save();

        $bt = $this->createBankTransaction([
            'description' => $custom,
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$client.custom1'],
            ['search_key' => 'amount', 'operator' => '=', 'value' => '$invoice.amount'],
        ], ['matches_on_all' => true]);

        $invoice = $this->createInvoice([
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
    }

    public function testClientCustom2Contains()
    {
        $custom = Str::random(16);

        $this->client->custom_value2 = $custom;
        $this->client->save();

        $bt = $this->createBankTransaction([
            'description' => $custom,
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$client.custom2'],
            ['search_key' => 'amount', 'operator' => '=', 'value' => '$invoice.amount'],
        ], ['matches_on_all' => true]);

        $invoice = $this->createInvoice([
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
    }

    public function testClientCustom3Contains()
    {
        $custom = Str::random(16);

        $this->client->custom_value3 = $custom;
        $this->client->save();

        $bt = $this->createBankTransaction([
            'description' => $custom,
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$client.custom3'],
            ['search_key' => 'amount', 'operator' => '=', 'value' => '$invoice.amount'],
        ], ['matches_on_all' => true]);

        $invoice = $this->createInvoice([
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
    }

    public function testClientCustom4Contains()
    {
        $custom = Str::random(16);

        $this->client->custom_value4 = $custom;
        $this->client->save();

        $bt = $this->createBankTransaction([
            'description' => $custom,
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$client.custom4'],
            ['search_key' => 'amount', 'operator' => '=', 'value' => '$invoice.amount'],
        ], ['matches_on_all' => true]);

        $invoice = $this->createInvoice([
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
    }

    // ── matches_on_all logic ─────────────────────────────────────────

    public function testMatchesOnAllRequiresAllConditions()
    {
        $amount = rand(1000, 9999999);

        $bt = $this->createBankTransaction([
            'description' => Str::random(32),
            'amount' => $amount,
        ]);

        // Rule requires BOTH amount AND po_number match
        $this->createRule([
            ['search_key' => 'amount', 'operator' => '=', 'value' => '$invoice.amount'],
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$invoice.po_number'],
        ], ['matches_on_all' => true]);

        // Invoice matches amount but NOT po_number (description doesn't contain it)
        $this->createInvoice([
            'amount' => $amount,
            'balance' => $amount,
            'po_number' => 'NOMATCH-' . Str::random(20),
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);
    }

    public function testMatchesOnAnyRequiresOneCondition()
    {
        $amount = rand(1000, 9999999);

        $bt = $this->createBankTransaction([
            'description' => Str::random(32),
            'amount' => $amount,
        ]);

        // Rule requires ANY of amount OR po_number match
        $this->createRule([
            ['search_key' => 'amount', 'operator' => '=', 'value' => '$invoice.amount'],
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$invoice.po_number'],
        ], ['matches_on_all' => false]);

        // Invoice matches amount but NOT po_number
        $invoice = $this->createInvoice([
            'amount' => $amount,
            'balance' => $amount,
            'po_number' => 'NOMATCH-' . Str::random(20),
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($invoice->hashed_id, $bt->invoice_ids);
    }

    // ── Empty description guard ──────────────────────────────────────

    public function testEmptyDescriptionSkipsCreditMatching()
    {
        $amount = 100;

        $bt = $this->createBankTransaction([
            'description' => '',
            'amount' => $amount,
        ]);

        $this->createRule([
            ['search_key' => 'amount', 'operator' => '=', 'value' => '$invoice.amount'],
        ]);

        $this->createInvoice([
            'amount' => $amount,
            'balance' => $amount,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        // Empty description means matchCredit() is never called
        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);
    }

    public function testNullDescriptionSkipsCreditMatching()
    {
        $amount = 100;

        $bt = $this->createBankTransaction([
            'description' => null,
            'amount' => $amount,
        ]);

        $this->createRule([
            ['search_key' => 'amount', 'operator' => '=', 'value' => '$invoice.amount'],
        ]);

        $this->createInvoice([
            'amount' => $amount,
            'balance' => $amount,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);
    }

    // ── DEBIT rules ──────────────────────────────────────────────────

    public function testDebitDescriptionContains()
    {
        $keyword = Str::random(16);

        $bt = $this->createBankTransaction([
            'description' => 'Amazon purchase ' . $keyword,
            'amount' => 50,
            'base_type' => 'DEBIT',
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => $keyword],
        ], [
            'applies_to' => 'DEBIT',
            'vendor_id' => $this->vendor->id,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        $this->assertEquals($this->vendor->id, $bt->vendor_id);
    }

    public function testDebitDescriptionIs()
    {
        $desc = 'NetflixSubscription';

        $bt = $this->createBankTransaction([
            'description' => $desc,
            'amount' => 15.99,
            'base_type' => 'DEBIT',
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'is', 'value' => $desc],
        ], [
            'applies_to' => 'DEBIT',
            'vendor_id' => $this->vendor->id,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
    }

    public function testDebitDescriptionStartsWith()
    {
        $prefix = 'AMZN-' . Str::random(8);

        $bt = $this->createBankTransaction([
            'description' => $prefix . ' order 12345',
            'amount' => 99.99,
            'base_type' => 'DEBIT',
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'starts_with', 'value' => $prefix],
        ], [
            'applies_to' => 'DEBIT',
            'vendor_id' => $this->vendor->id,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
    }

    public function testDebitDescriptionIsEmpty()
    {
        $bt = $this->createBankTransaction([
            'description' => '',
            'amount' => 50,
            'base_type' => 'DEBIT',
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'is_empty', 'value' => ''],
        ], [
            'applies_to' => 'DEBIT',
            'vendor_id' => $this->vendor->id,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
    }

    public function testDebitAmountEquals()
    {
        $bt = $this->createBankTransaction([
            'description' => 'Some expense',
            'amount' => 250.00,
            'base_type' => 'DEBIT',
        ]);

        $this->createRule([
            ['search_key' => 'amount', 'operator' => '=', 'value' => '250.00'],
        ], [
            'applies_to' => 'DEBIT',
            'vendor_id' => $this->vendor->id,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
    }

    public function testDebitAmountGreaterThan()
    {
        $bt = $this->createBankTransaction([
            'description' => 'Large expense',
            'amount' => 500,
            'base_type' => 'DEBIT',
        ]);

        $this->createRule([
            ['search_key' => 'amount', 'operator' => '>', 'value' => '100'],
        ], [
            'applies_to' => 'DEBIT',
            'vendor_id' => $this->vendor->id,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
    }

    public function testDebitAmountLessThan()
    {
        $bt = $this->createBankTransaction([
            'description' => 'Small expense',
            'amount' => 5,
            'base_type' => 'DEBIT',
        ]);

        $this->createRule([
            ['search_key' => 'amount', 'operator' => '<', 'value' => '100'],
        ], [
            'applies_to' => 'DEBIT',
            'vendor_id' => $this->vendor->id,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
    }

    public function testDebitAmountGreaterThanOrEqual()
    {
        $bt = $this->createBankTransaction([
            'description' => 'Expense',
            'amount' => 100,
            'base_type' => 'DEBIT',
        ]);

        $this->createRule([
            ['search_key' => 'amount', 'operator' => '>=', 'value' => '100'],
        ], [
            'applies_to' => 'DEBIT',
            'vendor_id' => $this->vendor->id,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
    }

    public function testDebitAmountLessThanOrEqual()
    {
        $bt = $this->createBankTransaction([
            'description' => 'Expense',
            'amount' => 100,
            'base_type' => 'DEBIT',
        ]);

        $this->createRule([
            ['search_key' => 'amount', 'operator' => '<=', 'value' => '100'],
        ], [
            'applies_to' => 'DEBIT',
            'vendor_id' => $this->vendor->id,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
    }

    public function testDebitAmountEqualsFails()
    {
        $bt = $this->createBankTransaction([
            'description' => 'Expense',
            'amount' => 100,
            'base_type' => 'DEBIT',
        ]);

        $this->createRule([
            ['search_key' => 'amount', 'operator' => '=', 'value' => '200'],
        ], [
            'applies_to' => 'DEBIT',
            'vendor_id' => $this->vendor->id,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);
    }

    // ── Debit matches_on_all ─────────────────────────────────────────

    public function testDebitMatchAllRequiresBothConditions()
    {
        $keyword = Str::random(16);

        $bt = $this->createBankTransaction([
            'description' => $keyword,
            'amount' => 100,
            'base_type' => 'DEBIT',
        ]);

        // Requires BOTH description AND amount match
        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => $keyword],
            ['search_key' => 'amount', 'operator' => '=', 'value' => '999'], // Won't match
        ], [
            'applies_to' => 'DEBIT',
            'matches_on_all' => true,
            'vendor_id' => $this->vendor->id,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        // Should NOT match because amount doesn't match
        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);
    }

    public function testDebitMatchAllSucceedsWhenBothMatch()
    {
        $keyword = Str::random(16);

        $bt = $this->createBankTransaction([
            'description' => $keyword,
            'amount' => 100,
            'base_type' => 'DEBIT',
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => $keyword],
            ['search_key' => 'amount', 'operator' => '=', 'value' => '100'],
        ], [
            'applies_to' => 'DEBIT',
            'matches_on_all' => true,
            'vendor_id' => $this->vendor->id,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
    }

    public function testDebitMatchAnySucceedsWithOneCondition()
    {
        $keyword = Str::random(16);

        $bt = $this->createBankTransaction([
            'description' => $keyword,
            'amount' => 100,
            'base_type' => 'DEBIT',
        ]);

        // Only description matches, amount does NOT
        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => $keyword],
            ['search_key' => 'amount', 'operator' => '=', 'value' => '999'],
        ], [
            'applies_to' => 'DEBIT',
            'matches_on_all' => false,
            'vendor_id' => $this->vendor->id,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
    }

    // ── Debit first rule wins (regression for break bug) ─────────────

    public function testDebitFirstRuleWinsAndStopsProcessing()
    {
        $keyword = Str::random(16);

        $vendor1 = Vendor::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $vendor2 = Vendor::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $bt = $this->createBankTransaction([
            'description' => $keyword,
            'amount' => 100,
            'base_type' => 'DEBIT',
        ]);

        // First rule
        $rule1 = $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => $keyword],
        ], [
            'applies_to' => 'DEBIT',
            'vendor_id' => $vendor1->id,
        ]);

        // Second rule also matches
        $rule2 = $this->createRule([
            ['search_key' => 'amount', 'operator' => '=', 'value' => '100'],
        ], [
            'applies_to' => 'DEBIT',
            'vendor_id' => $vendor2->id,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        // Should have matched FIRST rule, not second — the break should exit the outer loop
        $this->assertEquals($rule1->id, $bt->bank_transaction_rule_id);
        $this->assertEquals($vendor1->id, $bt->vendor_id);
    }

    // ── Debit auto_convert creates expense ───────────────────────────

    public function testDebitAutoConvertCreatesExpense()
    {
        $keyword = Str::random(16);

        $bt = $this->createBankTransaction([
            'description' => $keyword,
            'amount' => 75.50,
            'base_type' => 'DEBIT',
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => $keyword],
        ], [
            'applies_to' => 'DEBIT',
            'auto_convert' => true,
            'vendor_id' => $this->vendor->id,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_CONVERTED, $bt->status_id);
        $this->assertNotNull($bt->expense_id);

        // Verify an expense was created linked to this transaction
        $expense = Expense::where('transaction_id', $bt->id)->first();
        $this->assertNotNull($expense);
        $this->assertEquals(75.50, $expense->amount);
        $this->assertEquals($this->vendor->id, $expense->vendor_id);
        $this->assertEquals($keyword, $expense->transaction_reference);
    }

    // ── Credit rules do not match DEBIT transactions ─────────────────

    public function testCreditRulesDoNotMatchDebitTransactions()
    {
        $bt = $this->createBankTransaction([
            'description' => Str::random(32),
            'amount' => 100,
            'base_type' => 'DEBIT',
        ]);

        $this->createRule([
            ['search_key' => 'amount', 'operator' => '=', 'value' => '$invoice.amount'],
        ], ['applies_to' => 'CREDIT']);

        $this->createInvoice([
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        // DEBIT transaction should NOT be matched by CREDIT rules
        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);
    }

    // ── Debit rules do not match CREDIT transactions ─────────────────

    public function testDebitRulesDoNotMatchCreditTransactions()
    {
        $keyword = Str::random(16);

        $bt = $this->createBankTransaction([
            'description' => $keyword,
            'amount' => 100,
            'base_type' => 'CREDIT',
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => $keyword],
        ], [
            'applies_to' => 'DEBIT',
            'vendor_id' => $this->vendor->id,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        // CREDIT transaction should NOT be matched by DEBIT rules
        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);
    }

    // ── Unknown search_key defaults to no match ──────────────────────

    public function testUnknownSearchKeyDoesNotMatch()
    {
        $bt = $this->createBankTransaction([
            'description' => Str::random(32),
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'amount', 'operator' => '=', 'value' => '$nonexistent.field'],
        ]);

        $this->createInvoice([
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);
    }

    // ── Priority: invoice number wins over amount ────────────────────

    public function testInvoiceNumberPriorityOverAmount()
    {
        $number = 'INV-' . Str::random(20);

        $bt = $this->createBankTransaction([
            'description' => $number,
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'description', 'operator' => 'contains', 'value' => '$invoice.number'],
            ['search_key' => 'amount', 'operator' => '=', 'value' => '$invoice.amount'],
        ], ['matches_on_all' => false]);

        // Invoice matching by number
        $invoiceByNumber = $this->createInvoice([
            'number' => $number,
            'amount' => 999, // Different amount
            'balance' => 999,
        ]);

        // Invoice matching by amount
        $this->createInvoice([
            'number' => 'DIFFERENT-' . Str::random(10),
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_MATCHED, $bt->status_id);
        // Priority 1 (invoice number) should win over Priority 3 (amount)
        $this->assertEquals($invoiceByNumber->hashed_id, $bt->invoice_ids);
    }

    // ── Deleted/paid invoices are excluded ────────────────────────────

    public function testDeletedInvoicesAreExcluded()
    {
        $bt = $this->createBankTransaction([
            'description' => Str::random(32),
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'amount', 'operator' => '=', 'value' => '$invoice.amount'],
        ]);

        // Create a DELETED invoice with matching amount
        $this->createInvoice([
            'amount' => 100,
            'balance' => 100,
            'is_deleted' => true,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);
    }

    public function testPaidInvoicesAreExcluded()
    {
        $bt = $this->createBankTransaction([
            'description' => Str::random(32),
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'amount', 'operator' => '=', 'value' => '$invoice.amount'],
        ]);

        // status_id 4 = paid
        $this->createInvoice([
            'amount' => 100,
            'balance' => 0,
            'status_id' => 4,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);
    }

    // ── Already-linked payments are excluded ──────────────────────────

    public function testAlreadyLinkedPaymentsAreExcluded()
    {
        $bt = $this->createBankTransaction([
            'description' => Str::random(32),
            'amount' => 100,
        ]);

        $this->createRule([
            ['search_key' => 'amount', 'operator' => '=', 'value' => '$payment.amount'],
        ]);

        // Payment already linked to another transaction
        $this->createPayment([
            'amount' => 100,
            'transaction_id' => 999,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);
    }

    // ── No rule = no match ─────────────────────────────────────────

    public function testNoRuleDoesNotMatchInvoiceNumber()
    {
        $number = 'INV-' . Str::random(20);

        $bt = $this->createBankTransaction([
            'description' => 'Payment for ' . $number,
            'amount' => 100,
        ]);

        // No rules configured — should NOT match without a rule
        $this->createInvoice([
            'number' => $number,
            'amount' => 100,
            'balance' => 100,
        ]);

        (new ProcessBankRules($bt))->run();
        $bt = $bt->fresh();

        $this->assertEquals(BankTransaction::STATUS_UNMATCHED, $bt->status_id);
    }
}
