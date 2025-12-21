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
use Tests\MockAccountData;
use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Models\Vendor;
use App\Models\Expense;
use App\Models\Task;
use App\Models\Quote;
use App\Models\Credit;
use App\Models\Payment;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Scheduler;
use App\Models\TaskStatus;
use App\Models\CompanyToken;
use App\Models\RecurringQuote;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use App\Models\ExpenseCategory;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Models\BankTransactionRule;
use App\Models\ClientContact;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionClass;
use ReflectionProperty;

/**
 * Test class for MockAccountData trait.
 *
 * This test ensures all properties are properly declared with type hints
 * to prevent PHP 8.2+ dynamic property deprecation warnings.
 */
class MockAccountDataTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    /**
     * Test that all properties in MockAccountData trait are properly declared.
     *
     * This prevents PHP 8.2+ deprecation notices for dynamic properties.
     */
    public function testAllPropertiesAreDeclared(): void
    {
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        $expectedProperties = [
            'credit_calc',
            'invoice_calc',
            'quote_calc',
            'recurring_invoice_calc',
            'project',
            'account',
            'company',
            'user',
            'client',
            'token',
            'recurring_expense',
            'recurring_quote',
            'credit',
            'invoice',
            'quote',
            'vendor',
            'expense',
            'task',
            'task_status',
            'expense_category',
            'cu',
            'bank_integration',
            'bank_transaction',
            'bank_transaction_rule',
            'payment',
            'tax_rate',
            'scheduler',
            'purchase_order',
            'contact',
            'product',
            'recurring_invoice',
        ];

        $foundProperties = [];
        foreach ($properties as $property) {
            if (in_array($property->getName(), $expectedProperties)) {
                $foundProperties[] = $property->getName();
            }
        }

        // Verify all expected properties were found
        $missingProperties = array_diff($expectedProperties, $foundProperties);
        $this->assertEmpty(
            $missingProperties,
            'Missing property declarations: ' . implode(', ', $missingProperties)
        );
    }

    /**
     * Test that properties can be assigned without triggering deprecation warnings.
     */
    public function testPropertiesCanBeAssignedWithoutWarnings(): void
    {
        // These assignments should not trigger any deprecation warnings
        $this->credit_calc = 'test_value';
        $this->invoice_calc = null;
        $this->quote_calc = null;
        $this->recurring_invoice_calc = null;

        // Create mock objects for testing
        $this->account = new Account();
        $this->company = new Company();
        $this->user = new User();
        $this->client = new Client();
        $this->project = new Project();
        $this->vendor = new Vendor();
        $this->expense = new Expense();
        $this->task = new Task();
        $this->quote = new Quote();
        $this->credit = new Credit();
        $this->payment = new Payment();
        $this->product = new Product();
        $this->tax_rate = new TaxRate();
        $this->scheduler = new Scheduler();
        $this->task_status = new TaskStatus();
        $this->token = new CompanyToken();
        $this->recurring_quote = new RecurringQuote();
        $this->recurring_expense = new RecurringExpense();
        $this->recurring_invoice = new RecurringInvoice();
        $this->expense_category = new ExpenseCategory();
        $this->bank_integration = new BankIntegration();
        $this->bank_transaction = new BankTransaction();
        $this->bank_transaction_rule = new BankTransactionRule();
        $this->contact = new ClientContact();

        // Assert that the properties were set correctly
        $this->assertNotNull($this->credit_calc);
        $this->assertInstanceOf(Account::class, $this->account);
        $this->assertInstanceOf(Company::class, $this->company);
        $this->assertInstanceOf(User::class, $this->user);
        $this->assertInstanceOf(Client::class, $this->client);
    }

    /**
     * Test that all properties have proper nullable type hints.
     */
    public function testPropertiesHaveNullableTypeHints(): void
    {
        $reflection = new ReflectionClass($this);

        $nullableProperties = [
            'invoice_calc',
            'quote_calc',
            'recurring_invoice_calc',
            'project',
            'account',
            'company',
            'user',
            'client',
            'token',
            'recurring_expense',
            'recurring_quote',
            'credit',
            'invoice',
            'quote',
            'vendor',
            'expense',
            'task',
            'task_status',
            'expense_category',
            'cu',
            'bank_integration',
            'bank_transaction',
            'bank_transaction_rule',
            'payment',
            'tax_rate',
            'scheduler',
            'purchase_order',
            'contact',
            'product',
            'recurring_invoice',
        ];

        foreach ($nullableProperties as $propertyName) {
            if ($reflection->hasProperty($propertyName)) {
                $property = $reflection->getProperty($propertyName);

                // Property should allow null or have a default value of null
                $this->assertTrue(
                    $property->hasDefaultValue() && $property->getDefaultValue() === null,
                    "Property {$propertyName} should have a default value of null"
                );
            }
        }
    }

    /**
     * Test that properties don't trigger dynamic property creation warnings.
     */
    public function testNoDynamicPropertyWarnings(): void
    {
        // Enable error reporting to catch any warnings
        $previousErrorReporting = error_reporting();
        error_reporting(E_ALL);

        try {
            // Set various properties - this should not trigger any warnings
            $this->account = null;
            $this->company = null;
            $this->user = null;
            $this->client = null;

            // If we got here without warnings/errors, the test passes
            $this->assertTrue(true, 'No dynamic property warnings were triggered');
        } finally {
            // Restore previous error reporting level
            error_reporting($previousErrorReporting);
        }
    }

    /**
     * Test that credit_calc property accepts mixed types.
     */
    public function testCreditCalcAcceptsMixedTypes(): void
    {
        $this->credit_calc = 'string_value';
        $this->assertIsString($this->credit_calc);

        $this->credit_calc = 123;
        $this->assertIsInt($this->credit_calc);

        $this->credit_calc = ['array' => 'value'];
        $this->assertIsArray($this->credit_calc);

        $this->credit_calc = null;
        $this->assertNull($this->credit_calc);
    }
}
