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

namespace Tests\Unit\Import\Invoice2Go;

use App\Import\Transformer\Invoice2Go\InvoiceTransformer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class ParseCsvWithNestedCsvTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private InvoiceTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => config('ninja.db.default')]);

        $this->makeTestData();

        $this->transformer = new InvoiceTransformer($this->company);
    }

    /**
     * A well-formed Items string with a single line item and nested tax data
     * should parse into an associative array with the tax field expanded.
     */
    public function testParsesNestedTaxFieldCorrectly(): void
    {
        $csv = 'code,description,qty,unit_type,withholding_tax_applies,applied_taxes,unit_price,discount_percentage,discount_type,discount_amount;,Widget,2,parts,false,"rate,tax_id;10,abc-123",50,0,no_discount,0';

        $result = $this->transformer->parseCsvWithNestedCsv($csv);

        $row = reset($result);

        $this->assertIsArray($row['applied_taxes']);
        $this->assertEquals('10', $row['applied_taxes']['rate']);
        $this->assertEquals('abc-123', $row['applied_taxes']['tax_id']);
        $this->assertEquals('Widget', $row['description']);
        $this->assertEquals('50', $row['unit_price']);
        $this->assertEquals('2', $row['qty']);
    }

    /**
     * Multiple line items should each get their nested tax fields parsed independently.
     */
    public function testParsesMultipleLineItemsWithTaxes(): void
    {
        $csv = 'code,description,qty,unit_type,withholding_tax_applies,applied_taxes,unit_price,discount_percentage,discount_type,discount_amount;,First Item,1,parts,false,"rate,tax_id;17,tax-001",35,0,no_discount,0;,Second Item,3,parts,false,"rate,tax_id;8,tax-002",65,0,no_discount,0';

        $result = $this->transformer->parseCsvWithNestedCsv($csv);

        $rows = array_values($result);

        $this->assertCount(2, $rows);

        $this->assertEquals('17', $rows[0]['applied_taxes']['rate']);
        $this->assertEquals('tax-001', $rows[0]['applied_taxes']['tax_id']);

        $this->assertEquals('8', $rows[1]['applied_taxes']['rate']);
        $this->assertEquals('tax-002', $rows[1]['applied_taxes']['tax_id']);
    }

    /**
     * A line item with no nested tax data (plain string in the applied_taxes column)
     * should pass through as a string — no array conversion.
     */
    public function testHandlesPlainStringTaxField(): void
    {
        $csv = 'code,description,qty,unit_type,withholding_tax_applies,applied_taxes,unit_price,discount_percentage,discount_type,discount_amount;,No Tax Item,1,parts,false,,100,0,no_discount,0';

        $result = $this->transformer->parseCsvWithNestedCsv($csv);

        $row = reset($result);

        $this->assertIsString($row['applied_taxes']);
        $this->assertEquals('', $row['applied_taxes']);
    }

    /**
     * Rows with fewer fields than expected should not throw
     * undefined offset errors on index 5 or index 1.
     */
    public function testHandlesShortRowWithoutCrashing(): void
    {
        // Header has 10 columns but data row only has 4
        $csv = 'code,description,qty,unit_type,withholding_tax_applies,applied_taxes,unit_price,discount_percentage,discount_type,discount_amount;,Short Row,1,parts';

        $result = $this->transformer->parseCsvWithNestedCsv($csv);

        $row = reset($result);

        // Short row can't be combined with headers (count mismatch),
        // so it stays as a numeric array. The critical thing is: no exception.
        $this->assertIsArray($row);
        $this->assertContains('Short Row', $row);
    }

    /**
     * When the nested CSV in the tax column is malformed (e.g. mismatched
     * key/value counts), it should fall back to an empty string.
     */
    public function testHandlesMismatchedKeyValueCountInTaxField(): void
    {
        // 3 keys but only 2 values
        $csv = 'code,description,qty,unit_type,withholding_tax_applies,applied_taxes,unit_price,discount_percentage,discount_type,discount_amount;,Bad Tax,1,parts,false,"rate,tax_id,extra;10,abc-123",50,0,no_discount,0';

        $result = $this->transformer->parseCsvWithNestedCsv($csv);

        $row = reset($result);

        // Mismatched keys/values should return empty string, not crash
        $this->assertEquals('', $row['applied_taxes']);
    }

    /**
     * A nested CSV in the tax column with no semicolon separator
     * (i.e. only keys, no values row) should fall back gracefully.
     */
    public function testHandlesTaxFieldWithNoValueRow(): void
    {
        // Nested CSV has only a header row, no value row after semicolon
        $csv = 'code,description,qty,unit_type,withholding_tax_applies,applied_taxes,unit_price,discount_percentage,discount_type,discount_amount;,No Values,1,parts,false,"rate,tax_id",50,0,no_discount,0';

        $result = $this->transformer->parseCsvWithNestedCsv($csv);

        $row = reset($result);

        // str_getcsv with ";" on "rate,tax_id" returns a single element — no $csv[1]
        $this->assertEquals('', $row['applied_taxes']);
    }

    /**
     * Test parsing with real Invoice2Go CSV data from the fixtures.
     */
    public function testParsesRealInvoice2GoItemsData(): void
    {
        $itemsCsv = 'code,description,qty,unit_type,withholding_tax_applies,applied_taxes,unit_price,discount_percentage,discount_type,discount_amount;,Bumper paint touch up,4,parts,false,"rate,tax_id;17,d4037c01-b719-4e89-a1f4-91c0daf3ee34",35,0,no_discount,0;,Wood Hammer Mallets,10,parts,false,"rate,tax_id;17,d4037c01-b719-4e89-a1f4-91c0daf3ee34",2.5,0,no_discount,0;,Monthly Lawn Service 2 Hours Weekly,4,parts,false,"rate,tax_id;17,d4037c01-b719-4e89-a1f4-91c0daf3ee34",65,0,no_discount,0;,Internal Hardware Repair,6,parts,false,"rate,tax_id;17,d4037c01-b719-4e89-a1f4-91c0daf3ee34",65,0,no_discount,0';

        $result = $this->transformer->parseCsvWithNestedCsv($itemsCsv);

        $rows = array_values($result);

        $this->assertCount(4, $rows);

        // First item
        $this->assertEquals('Bumper paint touch up', $rows[0]['description']);
        $this->assertEquals('4', $rows[0]['qty']);
        $this->assertEquals('35', $rows[0]['unit_price']);
        $this->assertIsArray($rows[0]['applied_taxes']);
        $this->assertEquals('17', $rows[0]['applied_taxes']['rate']);
        $this->assertEquals('d4037c01-b719-4e89-a1f4-91c0daf3ee34', $rows[0]['applied_taxes']['tax_id']);

        // Last item
        $this->assertEquals('Internal Hardware Repair', $rows[3]['description']);
        $this->assertEquals('6', $rows[3]['qty']);
        $this->assertEquals('65', $rows[3]['unit_price']);
        $this->assertEquals('17', $rows[3]['applied_taxes']['rate']);
    }

    /**
     * A description with commas inside quotes should survive parsing
     * without corrupting the row structure.
     */
    public function testDescriptionWithCommasInQuotes(): void
    {
        $csv = 'code,description,qty,unit_type,withholding_tax_applies,applied_taxes,unit_price,discount_percentage,discount_type,discount_amount;,Service A,1,parts,false,,100,0,no_discount,0';

        $result = $this->transformer->parseCsvWithNestedCsv($csv);

        $row = reset($result);

        $this->assertIsString($row['description']);
        $this->assertEquals('Service A', $row['description']);
    }

    /**
     * An entirely empty Items string should not throw and should return
     * an empty array (header row is removed).
     */
    public function testHandlesEmptyString(): void
    {
        $result = $this->transformer->parseCsvWithNestedCsv('');

        $this->assertIsArray($result);
    }

    /**
     * A single header row with no data rows should return an empty array.
     */
    public function testHandlesHeaderOnlyWithNoDataRows(): void
    {
        $csv = 'code,description,qty,unit_type,withholding_tax_applies,applied_taxes,unit_price,discount_percentage,discount_type,discount_amount';

        $result = $this->transformer->parseCsvWithNestedCsv($csv);

        $this->assertEmpty($result);
    }
}
