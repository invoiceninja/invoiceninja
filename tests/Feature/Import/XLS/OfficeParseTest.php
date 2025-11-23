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

namespace Tests\Feature\Import\XLS;

use App\Import\Providers\Csv;
use App\Import\Transformer\BaseTransformer;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Vendor;
use App\Utils\Traits\MakesHash;
use App\Utils\TruthSource;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\MockAccountData;
use Tests\TestCase;

class OfficeParseTest extends TestCase
{
    use MakesHash;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        config(['database.default' => config('ninja.db.default')]);

        $this->makeTestData();

        $this->withoutExceptionHandling();

    }

    public function test_parse_excel_file()
    {
        $inputFileType = 'Xlsx';
        $inputFileName = base_path('tests/Feature/Import/clients.xlsx');

        // Test 1: Verify file exists
        $this->assertFileExists($inputFileName, 'Excel file should exist');

        // Test 2: Create reader and verify it's valid
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
        $this->assertInstanceOf(\PhpOffice\PhpSpreadsheet\Reader\Xlsx::class, $reader, 'Reader should be Xlsx type');

        // Test 3: Configure reader
        $reader->setIgnoreRowsWithNoCells(true);
        $reader->setReadDataOnly(true);

        // Test 4: Load spreadsheet
        $spreadsheet = $reader->load($inputFileName);
        $this->assertInstanceOf(\PhpOffice\PhpSpreadsheet\Spreadsheet::class, $spreadsheet, 'Should load as Spreadsheet object');

        // Test 5: Verify spreadsheet has content
        $this->assertGreaterThan(0, $spreadsheet->getSheetCount(), 'Spreadsheet should have at least one sheet');

        // Test 6: Get first worksheet
        $worksheet = $spreadsheet->getActiveSheet();
        $this->assertInstanceOf(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::class, $worksheet, 'Should have active worksheet');

        // Test 7: Verify worksheet has data
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();

        $this->assertGreaterThan(0, $highestRow, 'Worksheet should have at least one row');
        $this->assertNotEmpty($highestColumn, 'Worksheet should have at least one column');

        // Test 8: Read and validate header row
        $headers = [];
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $cellValue = $worksheet->getCell($colLetter . '1')->getValue();
            if (!empty($cellValue)) {
                $headers[] = $cellValue;
            }
        }

        $this->assertNotEmpty($headers, 'Should have header row');
        $this->assertContains('Name', $headers, 'Should contain Name column');
        $this->assertContains('Email', $headers, 'Should contain Email column');

        // Test 9: Read and validate data rows
        $dataRows = [];
        for ($row = 2; $row <= $highestRow; $row++) {
            $rowData = [];
            for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $cellValue = $worksheet->getCell($colLetter . $row)->getValue();
                $rowData[] = $cellValue;
            }

            // Only add rows that have at least one non-empty cell
            if (array_filter($rowData, function ($value) { return !empty($value); })) {
                $dataRows[] = $rowData;
            }
        }

        $this->assertNotEmpty($dataRows, 'Should have data rows');
        $this->assertGreaterThan(0, count($dataRows), 'Should have at least one data row');

        // Test 10: Validate specific data structure
        foreach ($dataRows as $index => $rowData) {
            $this->assertIsArray($rowData, "Row {$index} should be an array");

            // Check that we have at least the name field
            $nameIndex = array_search('Name', $headers);
            if ($nameIndex !== false && isset($rowData[$nameIndex])) {
                $this->assertNotEmpty($rowData[$nameIndex], "Row {$index} should have a name");
            }
        }

        // Test 11: Verify spreadsheet properties
        $properties = $spreadsheet->getProperties();
        $this->assertInstanceOf(\PhpOffice\PhpSpreadsheet\Document\Properties::class, $properties, 'Should have document properties');

        // Test 12: Test cell access methods
        $firstCell = $worksheet->getCell('A1');
        $this->assertInstanceOf(\PhpOffice\PhpSpreadsheet\Cell\Cell::class, $firstCell, 'Should be able to access individual cells');

        // Test 13: Test range access
        $range = $worksheet->rangeToArray('A1:' . $highestColumn . '1');
        $this->assertIsArray($range, 'Should be able to get range as array');
        $this->assertNotEmpty($range, 'Range should not be empty');

        // Clean up
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    public function test_parse_excel_file_error_handling()
    {
        // Test with non-existent file
        $nonExistentFile = base_path('tests/Feature/Import/non_existent.xlsx');

        $this->expectException(\PhpOffice\PhpSpreadsheet\Reader\Exception::class);

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->load($nonExistentFile);
    }

    public function test_parse_excel_file_invalid_format()
    {
        // Test with wrong file type
        $csvFile = base_path('tests/Feature/Import/clients.csv');

        $this->expectException(\PhpOffice\PhpSpreadsheet\Reader\Exception::class);

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->load($csvFile);
    }

    public function test_parse_excel_file_empty_sheet()
    {
        $inputFileName = base_path('tests/Feature/Import/clients.xlsx');

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->setIgnoreRowsWithNoCells(true);
        $spreadsheet = $reader->load($inputFileName);

        $worksheet = $spreadsheet->getActiveSheet();

        // Test that we can handle empty cells gracefully
        $emptyCell = $worksheet->getCell('Z999');
        $this->assertInstanceOf(\PhpOffice\PhpSpreadsheet\Cell\Cell::class, $emptyCell);
        $this->assertNull($emptyCell->getValue());

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    public function test_parse_excel_file_with_format_detection()
    {
        $inputFileName = base_path('tests/Feature/Import/clients.xlsx');

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(false); // Important: Keep formatting info
        $spreadsheet = $reader->load($inputFileName);

        $worksheet = $spreadsheet->getActiveSheet();

        // Test cell format detection and data extraction
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        for ($row = 1; $row <= min(5, $highestRow); $row++) {
            for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $cell = $worksheet->getCell($colLetter . $row);

                // Get the raw value (unformatted)
                $rawValue = $cell->getValue();

                // Get the formatted value (as displayed in Excel)
                $formattedValue = $cell->getFormattedValue();

                // Get the calculated value (for formulas)
                $calculatedValue = $cell->getCalculatedValue();

                // Get the data type
                $dataType = $cell->getDataType();

                // Get the number format
                $numberFormat = $cell->getStyle()->getNumberFormat()->getFormatCode();

                // Get the cell format type
                $formatType = $cell->getStyle()->getNumberFormat()->getBuiltInFormatCode();

                // Log cell information for analysis
                if (!empty($rawValue)) {
                    nlog("Cell {$colLetter}{$row}:");
                    nlog("  Raw Value: " . var_export($rawValue, true));
                    nlog("  Formatted Value: " . var_export($formattedValue, true));
                    nlog("  Calculated Value: " . var_export($calculatedValue, true));
                    nlog("  Data Type: " . $dataType);
                    nlog("  Number Format: " . $numberFormat);
                    nlog("  Format Type: " . $formatType);

                    // Test specific format detection
                    $this->assertIsString($dataType, 'Data type should be a string');
                    $this->assertIsString($numberFormat, 'Number format should be a string');

                    // Test data type constants
                    $validDataTypes = [
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING,
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC,
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_BOOL,
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NULL,
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_INLINE,
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_ERROR,
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA,
                    ];

                    $this->assertContains($dataType, $validDataTypes, 'Data type should be valid');
                }
            }
        }

        // Test specific format detection methods
        $this->test_specific_format_detection($worksheet);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    private function test_specific_format_detection($worksheet)
    {
        // Test date detection
        $dateCell = $worksheet->getCell('A1'); // Assuming first cell might be a date
        if ($dateCell->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC) {
            $numberFormat = $dateCell->getStyle()->getNumberFormat()->getFormatCode();

            // Check if it's a date format
            $isDate = $this->isDateTimeFormat($numberFormat);

            if ($isDate) {
                $rawValue = $dateCell->getValue();
                $formattedDate = $dateCell->getFormattedValue();

                nlog("Date Cell Found:");
                nlog("  Raw Value (Excel timestamp): " . $rawValue);
                nlog("  Formatted Date: " . $formattedDate);
                nlog("  Number Format: " . $numberFormat);

                // Convert Excel timestamp to PHP DateTime
                if (is_numeric($rawValue)) {
                    $phpDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($rawValue);
                    nlog("  PHP DateTime: " . $phpDate->format('Y-m-d H:i:s'));

                    $this->assertInstanceOf(\DateTime::class, $phpDate, 'Should convert Excel date to PHP DateTime');
                }
            }
        }

        // Test currency detection
        $currencyCell = $worksheet->getCell('B1'); // Assuming second cell might be currency
        if ($currencyCell->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC) {
            $numberFormat = $currencyCell->getStyle()->getNumberFormat()->getFormatCode();

            // Check if it's a currency format
            $isCurrency = strpos($numberFormat, '$') !== false ||
                         strpos($numberFormat, '€') !== false ||
                         strpos($numberFormat, '£') !== false ||
                         strpos($numberFormat, '[$') !== false;

            if ($isCurrency) {
                $rawValue = $currencyCell->getValue();
                $formattedCurrency = $currencyCell->getFormattedValue();

                nlog("Currency Cell Found:");
                nlog("  Raw Value: " . $rawValue);
                nlog("  Formatted Currency: " . $formattedCurrency);
                nlog("  Number Format: " . $numberFormat);

                $this->assertIsNumeric($rawValue, 'Currency raw value should be numeric');
            }
        }

        // Test percentage detection
        $percentageCell = $worksheet->getCell('C1'); // Assuming third cell might be percentage
        if ($percentageCell->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC) {
            $numberFormat = $percentageCell->getStyle()->getNumberFormat()->getFormatCode();

            // Check if it's a percentage format
            $isPercentage = strpos($numberFormat, '%') !== false;

            if ($isPercentage) {
                $rawValue = $percentageCell->getValue();
                $formattedPercentage = $percentageCell->getFormattedValue();

                nlog("Percentage Cell Found:");
                nlog("  Raw Value (decimal): " . $rawValue);
                nlog("  Formatted Percentage: " . $formattedPercentage);
                nlog("  Number Format: " . $numberFormat);

                $this->assertIsNumeric($rawValue, 'Percentage raw value should be numeric');
            }
        }
    }

    /**
     * Practical helper method showing how to extract and process different data types
     */
    public function test_practical_format_extraction()
    {
        $inputFileName = base_path('tests/Feature/Import/clients.xlsx');

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($inputFileName);

        $worksheet = $spreadsheet->getActiveSheet();

        // Example: Extract and process data with format awareness
        $processedData = $this->extractFormattedData($worksheet);

        nlog("Processed Data:");
        nlog($processedData);

        $this->assertIsArray($processedData, 'Should return processed data array');

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    /**
     * Extract data from Excel with format awareness
     */
    private function extractFormattedData($worksheet)
    {
        $data = [];
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        // Get headers first
        $headers = [];
        for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $cellValue = $worksheet->getCell($colLetter . '1')->getValue();
            if (!empty($cellValue)) {
                $headers[] = $cellValue;
            }
        }

        // Process data rows
        for ($row = 2; $row <= $highestRow; $row++) {
            $rowData = [];
            $hasData = false;

            for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $cell = $worksheet->getCell($colLetter . $row);

                $processedValue = $this->processCellValue($cell);

                if ($processedValue !== null) {
                    $hasData = true;
                }

                $rowData[] = $processedValue;
            }

            if ($hasData) {
                $data[] = array_combine($headers, $rowData);
            }
        }

        return $data;
    }

    /**
     * Process individual cell value based on its format
     */
    private function processCellValue($cell)
    {
        $rawValue = $cell->getValue();

        if (empty($rawValue)) {
            return null;
        }

        $dataType = $cell->getDataType();
        $numberFormat = $cell->getStyle()->getNumberFormat()->getFormatCode();
        $builtInFormat = $cell->getStyle()->getNumberFormat()->getBuiltInFormatCode();

        // Log format information for debugging
        nlog("Processing cell with:");
        nlog("  Data Type: " . $dataType);
        nlog("  Number Format: " . $numberFormat);
        nlog("  Built-in Format: " . $builtInFormat);
        nlog("  Raw Value: " . var_export($rawValue, true));

        switch ($dataType) {
            case \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC:
                return $this->processNumericValue($cell, $rawValue, $numberFormat);

            case \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING:
                return $this->processStringValue($cell, $rawValue, $numberFormat);

            case \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_BOOL:
                return (bool) $rawValue;

            case \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA:
                return $this->processFormulaValue($cell, $rawValue, $numberFormat);

            default:
                return $rawValue;
        }
    }

    /**
     * Process numeric values (dates, currency, percentages, etc.)
     */
    private function processNumericValue($cell, $rawValue, $numberFormat)
    {
        // Check if it's a date
        if ($this->isDateTimeFormat($numberFormat)) {
            $phpDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($rawValue);
            return [
                'type' => 'date',
                'raw' => $rawValue,
                'formatted' => $cell->getFormattedValue(),
                'php_date' => $phpDate->format('Y-m-d H:i:s'),
                'timestamp' => $phpDate->getTimestamp()
            ];
        }

        // Check if it's currency
        if (strpos($numberFormat, '$') !== false ||
            strpos($numberFormat, '€') !== false ||
            strpos($numberFormat, '£') !== false ||
            strpos($numberFormat, '[$') !== false) {
            return [
                'type' => 'currency',
                'raw' => (float) $rawValue,
                'formatted' => $cell->getFormattedValue(),
                'decimal' => (float) $rawValue
            ];
        }

        // Check if it's percentage
        if (strpos($numberFormat, '%') !== false) {
            return [
                'type' => 'percentage',
                'raw' => (float) $rawValue,
                'formatted' => $cell->getFormattedValue(),
                'decimal' => (float) $rawValue,
                'percentage' => (float) $rawValue * 100
            ];
        }

        // Regular number
        return [
            'type' => 'number',
            'raw' => (float) $rawValue,
            'formatted' => $cell->getFormattedValue(),
            'decimal' => (float) $rawValue
        ];
    }

    /**
     * Process string values with Excel format hints
     */
    private function processStringValue($cell, $rawValue, $numberFormat)
    {
        // Check if Excel suggests this should be numeric despite being stored as string
        $shouldBeNumeric = $this->shouldStringBeNumeric($numberFormat, $rawValue);

        if ($shouldBeNumeric) {
            $extractedValue = $this->extractCurrencyFromString($rawValue);
            if ($extractedValue !== null) {
                return [
                    'type' => 'currency_string',
                    'raw' => (string) $rawValue,
                    'formatted' => $cell->getFormattedValue(),
                    'extracted_decimal' => $extractedValue,
                    'original_string' => (string) $rawValue,
                    'excel_hint' => $numberFormat
                ];
            }
        }

        return [
            'type' => 'string',
            'raw' => (string) $rawValue,
            'formatted' => $cell->getFormattedValue(),
            'trimmed' => trim((string) $rawValue)
        ];
    }

    /**
     * Process formula values
     */
    private function processFormulaValue($cell, $rawValue, $numberFormat)
    {
        $calculatedValue = $cell->getCalculatedValue();

        return [
            'type' => 'formula',
            'raw' => $rawValue,
            'calculated' => $calculatedValue,
            'formatted' => $cell->getFormattedValue(),
            'processed' => $this->processNumericValue($cell, $calculatedValue, $numberFormat)
        ];
    }

    /**
     * Check if a number format code represents a date/time format
     */
    private function isDateTimeFormat($formatCode)
    {
        // Common date/time format patterns
        $datePatterns = [
            'dd', 'mm', 'yy', 'yyyy', 'm', 'd', 'h', 'hh', 'ss', 'am/pm', 'a/p',
            'dd/mm', 'mm/dd', 'dd-mm', 'mm-dd', 'dd/mm/yy', 'dd/mm/yyyy',
            'mm/dd/yy', 'mm/dd/yyyy', 'yyyy-mm-dd', 'dd-mm-yyyy',
            'h:mm', 'hh:mm', 'h:mm:ss', 'hh:mm:ss', 'm/d/yy h:mm',
            'dd/mm/yyyy hh:mm', 'yyyy-mm-dd hh:mm:ss'
        ];

        $formatCode = strtolower($formatCode);

        foreach ($datePatterns as $pattern) {
            if (strpos($formatCode, strtolower($pattern)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract currency value from string using Excel's formatting hints
     */
    private function extractCurrencyFromString($value)
    {
        if (!is_string($value)) {
            return null;
        }

        // Handle different currency formats
        $value = trim($value);

        // Remove common currency symbols
        $currencySymbols = ['$', '€', '£', '¥', '₹', '₽', '₩', '₪', '₦', '₡', '₱', '₲', '₴', '₸', '₺', '₼', '₾', '₿'];
        foreach ($currencySymbols as $symbol) {
            $value = str_replace($symbol, '', $value);
        }
        $value = trim($value);

        // Check if it's a valid number after removing currency symbols
        if (is_numeric($value)) {
            return (float) $value;
        }

        // Handle comma-separated thousands
        if (strpos($value, ',') !== false) {
            // Check if it's US format (comma as thousands separator)
            if (preg_match('/^[0-9,]+\.?[0-9]*$/', $value)) {
                $cleaned = str_replace(',', '', $value);
                if (is_numeric($cleaned)) {
                    return (float) $cleaned;
                }
            }

            // Check if it's European format (dot as thousands separator, comma as decimal)
            if (preg_match('/^[0-9]+\.[0-9]{3},[0-9]{2}$/', $value)) {
                $cleaned = str_replace('.', '', $value);
                $cleaned = str_replace(',', '.', $cleaned);
                if (is_numeric($cleaned)) {
                    return (float) $cleaned;
                }
            }
        }

        return null;
    }

    /**
     * Check if a string should be treated as numeric based on Excel formatting
     */
    private function shouldStringBeNumeric($numberFormat, $rawValue)
    {
        // If Excel has a numeric format but the value is stored as string
        if ($numberFormat !== '@' && is_string($rawValue)) {
            // Check if the string looks like a number
            return $this->extractCurrencyFromString($rawValue) !== null;
        }

        return false;
    }

    /**
     * Test currency string extraction specifically
     */
    public function test_currency_string_extraction()
    {
        // Test the currency extraction method directly
        $testCases = [
            '$313.71' => 313.71,
            '$1,234.56' => 1234.56,
            '€500.00' => 500.00,
            '£250.75' => 250.75,
            '¥1000' => 1000.0,
            '100.50' => 100.50, // No currency symbol
            'Invalid' => null,   // Invalid string
            '' => null,          // Empty string
        ];

        foreach ($testCases as $input => $expected) {
            $result = $this->extractCurrencyFromString($input);
            $this->assertEquals($expected, $result, "Failed to extract currency from: {$input}");
        }

        // Test with actual Excel file
        $inputFileName = base_path('tests/Feature/Import/clients.xlsx');

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($inputFileName);

        $worksheet = $spreadsheet->getActiveSheet();

        // Look for the problematic cell D3
        $cell = $worksheet->getCell('E3');
        $rawValue = $cell->getValue();
        $formattedValue = $cell->getFormattedValue();
        $dataType = $cell->getDataType();

        nlog("Cell D3 Analysis:");
        nlog("  Raw Value: " . var_export($rawValue, true));
        nlog("  Formatted Value: " . var_export($formattedValue, true));
        nlog("  Data Type: " . $dataType);

        if ($dataType === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING) {
            $extractedCurrency = $this->extractCurrencyFromString($rawValue);
            nlog("  Extracted Currency: " . var_export($extractedCurrency, true));

            if ($extractedCurrency !== null) {
                $this->assertIsFloat($extractedCurrency, 'Should extract numeric value from currency string');
                $this->assertEquals(313.71, $extractedCurrency, 'Should extract correct value from "$313.71"');
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    /**
     * Test currency column extraction and type analysis
     */
    public function test_currency_column_analysis()
    {
        $inputFileName = base_path('tests/Feature/Import/clients.xlsx');

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($inputFileName);

        $worksheet = $spreadsheet->getActiveSheet();

        // Find the Currency column
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        $currencyColumnIndex = null;
        $currencyColumnLetter = null;

        // Find the Currency column header
        for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $cellValue = $worksheet->getCell($colLetter . '1')->getValue();

            if (strtolower(trim($cellValue)) === 'currency') {
                $currencyColumnIndex = $colIndex;
                $currencyColumnLetter = $colLetter;
                break;
            }
        }

        $this->assertNotNull($currencyColumnIndex, 'Currency column should be found');

        nlog("Currency Column Found:");
        nlog("  Column Letter: " . $currencyColumnLetter);
        nlog("  Column Index: " . $currencyColumnIndex);

        // Analyze the Currency column data
        $highestRow = $worksheet->getHighestRow();
        $currencyData = [];

        for ($row = 2; $row <= $highestRow; $row++) {
            $cell = $worksheet->getCell($currencyColumnLetter . $row);
            $rawValue = $cell->getValue();
            $formattedValue = $cell->getFormattedValue();
            $dataType = $cell->getDataType();
            $numberFormat = $cell->getStyle()->getNumberFormat()->getFormatCode();
            $builtInFormat = $cell->getStyle()->getNumberFormat()->getBuiltInFormatCode();

            if (!empty($rawValue)) {
                $cellInfo = [
                    'row' => $row,
                    'raw_value' => $rawValue,
                    'formatted_value' => $formattedValue,
                    'data_type' => $dataType,
                    'number_format' => $numberFormat,
                    'built_in_format' => $builtInFormat,
                    'data_type_name' => $this->getDataTypeName($dataType)
                ];

                $currencyData[] = $cellInfo;

                nlog("Row {$row}:");
                nlog("  Raw Value: " . var_export($rawValue, true));
                nlog("  Formatted Value: " . var_export($formattedValue, true));
                nlog("  Data Type: " . $dataType . " (" . $this->getDataTypeName($dataType) . ")");
                nlog("  Number Format: " . $numberFormat);
                nlog("  Built-in Format: " . $builtInFormat);
            }
        }

        // Analyze the data types found
        $dataTypes = array_unique(array_column($currencyData, 'data_type'));
        $numberFormats = array_unique(array_column($currencyData, 'number_format'));

        nlog("Currency Column Analysis:");
        nlog("  Total non-empty cells: " . count($currencyData));
        nlog("  Data types found: " . implode(', ', $dataTypes));
        nlog("  Number formats found: " . implode(', ', $numberFormats));

        // Test specific expectations
        $this->assertNotEmpty($currencyData, 'Currency column should have data');

        // Check if all values are of the same type
        if (count($dataTypes) === 1) {
            nlog("  All values are of type: " . $dataTypes[0]);
        } else {
            nlog("  Mixed data types detected: " . implode(', ', $dataTypes));
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    /**
     * Get human-readable data type name
     */
    private function getDataTypeName($dataType)
    {
        $typeMap = [
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING => 'String',
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC => 'Numeric',
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_BOOL => 'Boolean',
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NULL => 'Null',
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_INLINE => 'Inline',
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_ERROR => 'Error',
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA => 'Formula',
        ];

        return $typeMap[$dataType] ?? 'Unknown';
    }

    /**
     * Test creating Excel file with localization settings
     */
    public function test_create_excel_with_localization()
    {
        // Create a new spreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();

        // Set up different localization scenarios
        $testCases = [
            'US_Dollar' => [
                'locale' => 'en_US',
                'currency_code' => 'USD',
                'currency_symbol' => '$',
                'number_format' => '#,##0.00',
                'date_format' => 'm/d/yyyy',
                'values' => [
                    ['Name', 'Amount', 'Date', 'Percentage'],
                    ['John Doe', 1234.56, '2023-12-25', 0.15],
                    ['Jane Smith', -567.89, '2023-12-26', 0.25],
                    ['Bob Wilson', 9999.99, '2023-12-27', 0.75],
                ]
            ],
            'European_Euro' => [
                'locale' => 'de_DE',
                'currency_code' => 'EUR',
                'currency_symbol' => '€',
                'number_format' => '#,##0.00',
                'date_format' => 'dd.mm.yyyy',
                'values' => [
                    ['Name', 'Amount', 'Date', 'Percentage'],
                    ['Hans Müller', 1234.56, '2023-12-25', 0.15],
                    ['Anna Schmidt', -567.89, '2023-12-26', 0.25],
                    ['Klaus Weber', 9999.99, '2023-12-27', 0.75],
                ]
            ],
            'UK_Pound' => [
                'locale' => 'en_GB',
                'currency_code' => 'GBP',
                'currency_symbol' => '£',
                'number_format' => '#,##0.00',
                'date_format' => 'dd/mm/yyyy',
                'values' => [
                    ['Name', 'Amount', 'Date', 'Percentage'],
                    ['John Smith', 1234.56, '2023-12-25', 0.15],
                    ['Mary Jones', -567.89, '2023-12-26', 0.25],
                    ['David Brown', 9999.99, '2023-12-27', 0.75],
                ]
            ]
        ];

        $currentRow = 1;

        foreach ($testCases as $testName => $config) {
            nlog("Creating {$testName} section:");
            nlog("  Locale: " . $config['locale']);
            nlog("  Currency: " . $config['currency_code'] . " (" . $config['currency_symbol'] . ")");

            // Add section header
            $worksheet->setCellValue("A{$currentRow}", $testName);
            $worksheet->getStyle("A{$currentRow}")->getFont()->setBold(true);
            $currentRow++;

            // Add data with localization
            foreach ($config['values'] as $rowIndex => $rowData) {
                $row = $currentRow + $rowIndex;

                // Set raw values
                $worksheet->setCellValue("A{$row}", $rowData[0]); // Name (string)
                $worksheet->setCellValue("B{$row}", $rowData[1]); // Amount (numeric)
                $worksheet->setCellValue("C{$row}", $rowData[2]); // Date (string, will be converted)
                $worksheet->setCellValue("D{$row}", $rowData[3]); // Percentage (numeric)

                // Apply currency formatting to Amount column
                $currencyFormat = $this->getCurrencyFormat($config['currency_code'], $config['locale']);
                $worksheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode($currencyFormat);

                // Apply date formatting to Date column
                $dateFormat = $this->getDateFormat($config['locale']);
                $worksheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode($dateFormat);

                // Convert date string to Excel date
                if ($rowIndex > 0) { // Skip header row
                    $dateValue = \PhpOffice\PhpSpreadsheet\Shared\Date::stringToExcel($rowData[2]);
                    $worksheet->setCellValue("C{$row}", $dateValue);
                }

                // Apply percentage formatting to Percentage column
                $worksheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode('0.00%');

                nlog("  Row {$row}:");
                nlog("    Raw Amount: " . $rowData[1]);
                nlog("    Currency Format: " . $currencyFormat);
                nlog("    Date Format: " . $dateFormat);
            }

            $currentRow += count($config['values']) + 2; // Add spacing between sections
        }

        // Test reading back the formatted values
        $this->test_read_formatted_values($worksheet);

        // Save the file
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $outputFile = storage_path('app/test_localized.xlsx');
        $writer->save($outputFile);

        nlog("Excel file created: " . $outputFile);

        $this->assertFileExists($outputFile, 'Localized Excel file should be created');

        // Clean up
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    /**
     * Test reading back the formatted values
     */
    private function test_read_formatted_values($worksheet)
    {
        nlog("Reading back formatted values:");

        // Read a few cells to verify formatting
        $testCells = [
            'B2' => 'US Dollar amount',
            'B6' => 'European Euro amount',
            'B10' => 'UK Pound amount',
            'C2' => 'US Date',
            'C6' => 'European Date',
            'C10' => 'UK Date',
            'D2' => 'US Percentage',
            'D6' => 'European Percentage',
            'D10' => 'UK Percentage',
        ];

        foreach ($testCells as $cellAddress => $description) {
            $cell = $worksheet->getCell($cellAddress);
            $rawValue = $cell->getValue();
            $formattedValue = $cell->getFormattedValue();
            $numberFormat = $cell->getStyle()->getNumberFormat()->getFormatCode();

            nlog("  {$description} ({$cellAddress}):");
            nlog("    Raw Value: " . var_export($rawValue, true));
            nlog("    Formatted Value: " . var_export($formattedValue, true));
            nlog("    Number Format: " . $numberFormat);
        }
    }

    /**
     * Get currency format based on locale and currency code
     */
    private function getCurrencyFormat($currencyCode, $locale)
    {
        $formats = [
            'USD' => [
                'en_US' => '$#,##0.00',
                'en_CA' => '$#,##0.00',
                'default' => '$#,##0.00'
            ],
            'EUR' => [
                'de_DE' => '#,##0.00 €',
                'fr_FR' => '#,##0.00 €',
                'it_IT' => '#,##0.00 €',
                'es_ES' => '#,##0.00 €',
                'default' => '#,##0.00 €'
            ],
            'GBP' => [
                'en_GB' => '£#,##0.00',
                'default' => '£#,##0.00'
            ],
            'JPY' => [
                'ja_JP' => '¥#,##0',
                'default' => '¥#,##0'
            ],
            'default' => '#,##0.00'
        ];

        return $formats[$currencyCode][$locale] ??
               $formats[$currencyCode]['default'] ??
               $formats['default'];
    }

    /**
     * Get date format based on locale
     */
    private function getDateFormat($locale)
    {
        $formats = [
            'en_US' => 'm/d/yyyy',
            'en_GB' => 'dd/mm/yyyy',
            'de_DE' => 'dd.mm.yyyy',
            'fr_FR' => 'dd/mm/yyyy',
            'it_IT' => 'dd/mm/yyyy',
            'es_ES' => 'dd/mm/yyyy',
            'ja_JP' => 'yyyy/m/d',
            'default' => 'yyyy-mm-dd'
        ];

        return $formats[$locale] ?? $formats['default'];
    }

    /**
     * Test different data injection methods for worksheets
     */
    public function test_worksheet_data_injection_methods()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();

        // Test 1: Single cell injection
        $this->test_single_cell_injection($worksheet);

        // Test 2: Row-by-row injection (like CSV)
        $this->test_row_by_row_injection($worksheet);

        // Test 3: Array-based injection (your preferred method)
        $this->test_array_based_injection($worksheet);

        // Test 4: Range-based injection
        $this->test_range_based_injection($worksheet);

        // Test 5: Bulk data injection
        $this->test_bulk_data_injection($worksheet);

        // Test 6: From array with headers
        $this->test_from_array_with_headers($worksheet);

        // Test 7: From array with formatting
        $this->test_from_array_with_formatting($worksheet);

        // Test 8: From array with mixed data types
        $this->test_from_array_with_mixed_types($worksheet);

        // Save the test file
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $outputFile = storage_path('app/test_data_injection.xlsx');
        $writer->save($outputFile);

        $this->assertFileExists($outputFile, 'Data injection test file should be created');

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    /**
     * Test 1: Single cell injection
     */
    private function test_single_cell_injection($worksheet)
    {
        nlog("=== Test 1: Single Cell Injection ===");

        // Method 1: Direct cell assignment
        $worksheet->setCellValue('A1', 'Single Cell Test');
        $worksheet->setCellValue('B1', 123.45);
        $worksheet->setCellValue('C1', '2023-12-25');

        // Method 2: Using coordinates
        $worksheet->setCellValue('A2', 'Column 1, Row 2');
        $worksheet->setCellValue('B2', 456.78);

        // Method 3: Using cell object
        $cell = $worksheet->getCell('A3');
        $cell->setValue('Cell Object Test');

        nlog("Single cell injection completed");
    }

    /**
     * Test 2: Row-by-row injection (like CSV)
     */
    private function test_row_by_row_injection($worksheet)
    {
        nlog("=== Test 2: Row-by-Row Injection (CSV Style) ===");

        $data = [
            ['Name', 'Age', 'Salary', 'Start Date'],
            ['John Doe', 30, 50000, '2023-01-15'],
            ['Jane Smith', 25, 45000, '2023-02-20'],
            ['Bob Wilson', 35, 60000, '2023-03-10'],
        ];

        $startRow = 5;
        foreach ($data as $rowIndex => $rowData) {
            $currentRow = $startRow + $rowIndex;

            foreach ($rowData as $colIndex => $value) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $worksheet->setCellValue($colLetter . $currentRow, $value);
            }
        }

        nlog("Row-by-row injection completed");
    }

    /**
     * Test 3: Array-based injection (your preferred method)
     */
    private function test_array_based_injection($worksheet)
    {
        nlog("=== Test 3: Array-Based Injection ===");

        $data = [
            ['Product', 'Price', 'Quantity', 'Total'],
            ['Laptop', 999.99, 2, 1999.98],
            ['Mouse', 25.50, 5, 127.50],
            ['Keyboard', 89.99, 3, 269.97],
        ];

        // Method 1: Using fromArray() - EASIEST for your use case
        $worksheet->fromArray($data, null, 'A10');

        // Method 2: Using fromArray() with custom null value
        $dataWithNulls = [
            ['Name', 'Email', 'Phone'],
            ['John', 'john@example.com', null],
            ['Jane', null, '555-1234'],
        ];
        $worksheet->fromArray($dataWithNulls, 'N/A', 'A15');

        nlog("Array-based injection completed");
    }

    /**
     * Test 4: Range-based injection
     */
    private function test_range_based_injection($worksheet)
    {
        nlog("=== Test 4: Range-Based Injection ===");

        $data = [
            ['ID', 'Name', 'Department'],
            [1, 'Alice', 'Engineering'],
            [2, 'Bob', 'Marketing'],
            [3, 'Charlie', 'Sales'],
        ];

        // Method 1: Using rangeToArray() in reverse
        $range = 'A20:D23';
        $worksheet->fromArray($data, null, 'A20');

        // Method 2: Using named ranges
        $worksheet->getParent()->addNamedRange(
            new \PhpOffice\PhpSpreadsheet\NamedRange('EmployeeData', $worksheet, 'A20:D23')
        );

        nlog("Range-based injection completed");
    }

    /**
     * Test 5: Bulk data injection
     */
    private function test_bulk_data_injection($worksheet)
    {
        nlog("=== Test 5: Bulk Data Injection ===");

        // Large dataset
        $bulkData = [];
        for ($i = 0; $i < 100; $i++) {
            $bulkData[] = [
                'ID-' . $i,
                'User-' . $i,
                rand(1000, 9999),
                date('Y-m-d', strtotime("+{$i} days"))
            ];
        }

        // Add headers
        array_unshift($bulkData, ['ID', 'Name', 'Score', 'Date']);

        // Bulk insert
        $worksheet->fromArray($bulkData, null, 'A25');

        nlog("Bulk data injection completed: " . count($bulkData) . " rows");
    }

    /**
     * Test 6: From array with headers
     */
    private function test_from_array_with_headers($worksheet)
    {
        nlog("=== Test 6: From Array with Headers ===");

        $headers = ['Product', 'Category', 'Price', 'Stock'];
        $data = [
            ['Laptop', 'Electronics', 999.99, 50],
            ['Desk', 'Furniture', 299.99, 25],
            ['Book', 'Education', 19.99, 100],
        ];

        // Method 1: Separate headers and data
        $worksheet->fromArray([$headers], null, 'A130');
        $worksheet->fromArray($data, null, 'A131');

        // Method 2: Combined with headers
        $fullData = array_merge([$headers], $data);
        $worksheet->fromArray($fullData, null, 'A140');

        nlog("Array with headers injection completed");
    }

    /**
     * Test 7: From array with formatting
     */
    private function test_from_array_with_formatting($worksheet)
    {
        nlog("=== Test 7: From Array with Formatting ===");

        $data = [
            ['Invoice', 'Amount', 'Date', 'Status'],
            ['INV-001', 1234.56, '2023-12-25', 'Paid'],
            ['INV-002', 567.89, '2023-12-26', 'Pending'],
            ['INV-003', 999.99, '2023-12-27', 'Overdue'],
        ];

        // Insert data
        $worksheet->fromArray($data, null, 'A150');

        // Apply formatting after insertion
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();

        // Format headers
        $worksheet->getStyle('A150:' . $highestColumn . '150')->getFont()->setBold(true);

        // Format currency column
        $worksheet->getStyle('B151:B' . $highestRow)->getNumberFormat()->setFormatCode('$#,##0.00');

        // Format date column
        $worksheet->getStyle('C151:C' . $highestRow)->getNumberFormat()->setFormatCode('m/d/yyyy');

        // Color code status
        $statusColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        for ($row = 151; $row <= $highestRow; $row++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($statusColumn);
            $statusCell = $worksheet->getCell($colLetter . $row);
            $status = $statusCell->getValue();

            switch ($status) {
                case 'Paid':
                    $statusCell->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                    $statusCell->getStyle()->getFill()->getStartColor()->setRGB('90EE90');
                    break;
                case 'Pending':
                    $statusCell->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                    $statusCell->getStyle()->getFill()->getStartColor()->setRGB('FFD700');
                    break;
                case 'Overdue':
                    $statusCell->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                    $statusCell->getStyle()->getFill()->getStartColor()->setRGB('FFB6C1');
                    break;
            }
        }

        nlog("Array with formatting injection completed");
    }

    /**
     * Test 8: From array with mixed data types
     */
    private function test_from_array_with_mixed_types($worksheet)
    {
        nlog("=== Test 8: From Array with Mixed Data Types ===");

        $mixedData = [
            ['Type', 'String', 'Number', 'Date', 'Boolean', 'Formula'],
            ['Text', 'Hello World', 42, '2023-12-25', true, '=B2*2'],
            ['Number', '123', 3.14159, '2023-12-26', false, '=SUM(B3:C3)'],
            ['Date', '2023-12-27', 100, '2023-12-27', true, '=TODAY()'],
        ];

        // Insert mixed data
        $worksheet->fromArray($mixedData, null, 'A170');

        // Apply type-specific formatting
        $highestRow = $worksheet->getHighestRow();

        // Format number column
        $worksheet->getStyle('C171:C' . $highestRow)->getNumberFormat()->setFormatCode('#,##0.00');

        // Format date column
        $worksheet->getStyle('D171:D' . $highestRow)->getNumberFormat()->setFormatCode('yyyy-mm-dd');

        // Format boolean column
        $worksheet->getStyle('E171:E' . $highestRow)->getNumberFormat()->setFormatCode('"Yes";"No"');

        nlog("Mixed data types injection completed");
    }

    /**
     * Test CSV-style vs Array-style performance comparison
     */
    public function test_csv_vs_array_performance()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();

        $testData = [];
        for ($i = 0; $i < 1000; $i++) {
            $testData[] = [
                'ID-' . $i,
                'Name-' . $i,
                rand(1000, 9999),
                date('Y-m-d', strtotime("+{$i} days")),
                rand(100, 999) / 100
            ];
        }

        // Test CSV-style (row by row)
        $startTime = microtime(true);
        $startRow = 1;
        foreach ($testData as $rowIndex => $rowData) {
            $currentRow = $startRow + $rowIndex;
            foreach ($rowData as $colIndex => $value) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $worksheet->setCellValue($colLetter . $currentRow, $value);
            }
        }
        $csvTime = microtime(true) - $startTime;

        // Test Array-style
        $startTime = microtime(true);
        $worksheet->fromArray($testData, null, 'A1001');
        $arrayTime = microtime(true) - $startTime;

        nlog("Performance Comparison:");
        nlog("  CSV-style (row by row): " . round($csvTime * 1000, 2) . "ms");
        nlog("  Array-style (fromArray): " . round($arrayTime * 1000, 2) . "ms");
        nlog("  Speed improvement: " . round(($csvTime / $arrayTime), 1) . "x faster");

        $this->assertLessThan($csvTime, $arrayTime, 'Array method should be faster than CSV method');

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    /**
     * Test column-based formatting
     */
    public function test_column_based_formatting()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();

        // Sample data
        $data = [
            ['Invoice', 'Amount', 'Date', 'Status', 'Percentage'],
            ['INV-001', 1234.56, '2023-12-25', 'Paid', 0.15],
            ['INV-002', 567.89, '2023-12-26', 'Pending', 0.25],
            ['INV-003', 999.99, '2023-12-27', 'Overdue', 0.75],
            ['INV-004', 2345.67, '2023-12-28', 'Paid', 0.50],
        ];

        // Insert data
        $worksheet->fromArray($data, null, 'A1');

        nlog("=== Column-Based Formatting Examples ===");

        // Method 1: Format entire columns using column letters
        $this->test_column_formatting_methods($worksheet);

        // Method 2: Format columns with dynamic detection
        $this->test_dynamic_column_formatting($worksheet);

        // Method 3: Format columns based on header names
        $this->test_header_based_column_formatting($worksheet);

        // Method 4: Format columns with conditional formatting
        $this->test_conditional_column_formatting($worksheet);

        // Save the test file
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $outputFile = storage_path('app/test_column_formatting.xlsx');
        $writer->save($outputFile);

        $this->assertFileExists($outputFile, 'Column formatting test file should be created');

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    /**
     * Test different column formatting methods
     */
    private function test_column_formatting_methods($worksheet)
    {
        nlog("--- Method 1: Direct Column Formatting ---");

        // Format entire columns using column letters
        $worksheet->getStyle('A:A')->getFont()->setBold(true); // Invoice column
        $worksheet->getStyle('B:B')->getNumberFormat()->setFormatCode('$#,##0.00'); // Amount column
        $worksheet->getStyle('C:C')->getNumberFormat()->setFormatCode('m/d/yyyy'); // Date column
        $worksheet->getStyle('D:D')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER); // Status column
        $worksheet->getStyle('E:E')->getNumberFormat()->setFormatCode('0.00%'); // Percentage column

        nlog("  Column A (Invoice): Bold font");
        nlog("  Column B (Amount): Currency format");
        nlog("  Column C (Date): Date format");
        nlog("  Column D (Status): Center aligned");
        nlog("  Column E (Percentage): Percentage format");
    }

    /**
     * Test dynamic column formatting
     */
    private function test_dynamic_column_formatting($worksheet)
    {
        nlog("--- Method 2: Dynamic Column Formatting ---");

        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        // Format each column based on its position
        for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $range = $colLetter . '1:' . $colLetter . $highestRow;

            switch ($colIndex) {
                case 1: // Invoice column
                    $worksheet->getStyle($range)->getFont()->setBold(true);
                    nlog("  Column {$colLetter}: Bold font");
                    break;
                case 2: // Amount column
                    $worksheet->getStyle($range)->getNumberFormat()->setFormatCode('$#,##0.00');
                    nlog("  Column {$colLetter}: Currency format");
                    break;
                case 3: // Date column
                    $worksheet->getStyle($range)->getNumberFormat()->setFormatCode('m/d/yyyy');
                    nlog("  Column {$colLetter}: Date format");
                    break;
                case 4: // Status column
                    $worksheet->getStyle($range)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    nlog("  Column {$colLetter}: Center aligned");
                    break;
                case 5: // Percentage column
                    $worksheet->getStyle($range)->getNumberFormat()->setFormatCode('0.00%');
                    nlog("  Column {$colLetter}: Percentage format");
                    break;
            }
        }
    }

    /**
     * Test header-based column formatting
     */
    private function test_header_based_column_formatting($worksheet)
    {
        nlog("--- Method 3: Header-Based Column Formatting ---");

        // Get headers from first row
        $headers = [];
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $headers[$colIndex] = $worksheet->getCell($colLetter . '1')->getValue();
        }

        // Format columns based on header names
        $highestRow = $worksheet->getHighestRow();
        foreach ($headers as $colIndex => $headerName) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $range = $colLetter . '2:' . $colLetter . $highestRow; // Skip header row

            switch (strtolower($headerName)) {
                case 'invoice':
                    $worksheet->getStyle($range)->getFont()->setBold(true);
                    nlog("  Column '{$headerName}': Bold font");
                    break;
                case 'amount':
                    $worksheet->getStyle($range)->getNumberFormat()->setFormatCode('$#,##0.00');
                    nlog("  Column '{$headerName}': Currency format");
                    break;
                case 'date':
                    $worksheet->getStyle($range)->getNumberFormat()->setFormatCode('m/d/yyyy');
                    nlog("  Column '{$headerName}': Date format");
                    break;
                case 'status':
                    $worksheet->getStyle($range)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    nlog("  Column '{$headerName}': Center aligned");
                    break;
                case 'percentage':
                    $worksheet->getStyle($range)->getNumberFormat()->setFormatCode('0.00%');
                    nlog("  Column '{$headerName}': Percentage format");
                    break;
            }
        }
    }

    /**
     * Test conditional column formatting
     */
    private function test_conditional_column_formatting($worksheet)
    {
        nlog("--- Method 4: Conditional Column Formatting ---");

        $highestRow = $worksheet->getHighestRow();

        // Format Status column with conditional colors
        $statusRange = 'D2:D' . $highestRow;
        $conditionalStyles = $worksheet->getStyle($statusRange)->getConditionalStyles();

        // Add conditional formatting for Status column
        $conditionalStyles[] = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
        $conditionalStyles[0]->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS);
        $conditionalStyles[0]->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_EQUAL);
        $conditionalStyles[0]->addCondition('"Paid"');
        $conditionalStyles[0]->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $conditionalStyles[0]->getStyle()->getFill()->getStartColor()->setRGB('90EE90');

        $conditionalStyles[] = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
        $conditionalStyles[1]->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS);
        $conditionalStyles[1]->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_EQUAL);
        $conditionalStyles[1]->addCondition('"Pending"');
        $conditionalStyles[1]->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $conditionalStyles[1]->getStyle()->getFill()->getStartColor()->setRGB('FFD700');

        $conditionalStyles[] = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
        $conditionalStyles[2]->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS);
        $conditionalStyles[2]->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_EQUAL);
        $conditionalStyles[2]->addCondition('"Overdue"');
        $conditionalStyles[2]->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $conditionalStyles[2]->getStyle()->getFill()->getStartColor()->setRGB('FFB6C1');

        $worksheet->getStyle($statusRange)->setConditionalStyles($conditionalStyles);

        nlog("  Status column: Conditional formatting applied");
    }

    /**
     * Test practical column formatting helper
     */
    public function test_practical_column_formatting()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();

        // Your data
        $data = [
            ['Invoice', 'Amount', 'Date', 'Status', 'Percentage'],
            ['INV-001', 1234.56, '2023-12-25', 'Paid', 0.15],
            ['INV-002', 567.89, '2023-12-26', 'Pending', 0.25],
            ['INV-003', 999.99, '2023-12-27', 'Overdue', 0.75],
        ];

        // Insert data
        $worksheet->fromArray($data, null, 'A1');

        // Apply column formatting using helper method
        $this->applyColumnFormatting($worksheet);

        // Save
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $outputFile = storage_path('app/test_practical_column_formatting.xlsx');
        $writer->save($outputFile);

        $this->assertFileExists($outputFile, 'Practical column formatting file should be created');

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    /**
     * Helper method for practical column formatting
     */
    private function applyColumnFormatting($worksheet)
    {
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        // Define column formats
        $columnFormats = [
            1 => ['type' => 'text', 'format' => 'bold'],
            2 => ['type' => 'currency', 'format' => '$#,##0.00'],
            3 => ['type' => 'date', 'format' => 'm/d/yyyy'],
            4 => ['type' => 'text', 'format' => 'center'],
            5 => ['type' => 'percentage', 'format' => '0.00%'],
        ];

        // Apply formatting to each column
        for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $range = $colLetter . '2:' . $colLetter . $highestRow; // Skip header

            if (isset($columnFormats[$colIndex])) {
                $format = $columnFormats[$colIndex];

                switch ($format['type']) {
                    case 'currency':
                        $worksheet->getStyle($range)->getNumberFormat()->setFormatCode($format['format']);
                        break;
                    case 'date':
                        $worksheet->getStyle($range)->getNumberFormat()->setFormatCode($format['format']);
                        break;
                    case 'percentage':
                        $worksheet->getStyle($range)->getNumberFormat()->setFormatCode($format['format']);
                        break;
                    case 'text':
                        if ($format['format'] === 'bold') {
                            $worksheet->getStyle($range)->getFont()->setBold(true);
                        } elseif ($format['format'] === 'center') {
                            $worksheet->getStyle($range)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                        }
                        break;
                }
            }
        }

        nlog("Applied column formatting to " . $highestColumnIndex . " columns");
    }

    /**
     * Test worksheet naming methods
     */
    public function test_worksheet_naming_methods()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        nlog("=== Worksheet Naming Methods ===");

        // Method 1: Name the active sheet
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Tax Report 2023');
        nlog("Method 1: Active sheet renamed to: " . $worksheet->getTitle());

        // Method 2: Create new worksheet with name
        $newWorksheet = $spreadsheet->createSheet();
        $newWorksheet->setTitle('Invoice Summary');
        nlog("Method 2: New sheet created with name: " . $newWorksheet->getTitle());

        // Method 3: Set active sheet by name
        $spreadsheet->setActiveSheetIndexByName('Invoice Summary');
        $activeSheet = $spreadsheet->getActiveSheet();
        nlog("Method 3: Active sheet is now: " . $activeSheet->getTitle());

        // Method 4: Get worksheet by name
        $taxWorksheet = $spreadsheet->getSheetByName('Tax Report 2023');
        nlog("Method 4: Retrieved sheet by name: " . $taxWorksheet->getTitle());

        // Method 5: List all worksheet names
        $sheetNames = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheetNames[] = $sheet->getTitle();
        }
        nlog("Method 5: All sheet names: " . implode(', ', $sheetNames));

        // Method 6: Check if worksheet exists by name
        $exists = $spreadsheet->getSheetByName('Tax Report 2023') !== null;
        nlog("Method 6: 'Tax Report 2023' exists: " . ($exists ? 'Yes' : 'No'));

        // Method 7: Remove worksheet by name
        $spreadsheet->removeSheetByIndex(1); // Remove the second sheet
        nlog("Method 7: Removed second sheet");

        // Method 8: Rename worksheet
        $worksheet->setTitle('Updated Tax Report 2023');
        nlog("Method 8: Renamed to: " . $worksheet->getTitle());

        // Method 9: Get worksheet index by name
        $index = $spreadsheet->getIndex($spreadsheet->getSheetByName('Updated Tax Report 2023'));
        nlog("Method 9: Sheet index: " . $index);

        // Method 10: Validate worksheet name
        $validName = $this->validateWorksheetName('Tax Report 2023');
        nlog("Method 10: Valid worksheet name: " . ($validName ? 'Yes' : 'No'));

        // Test specific expectations
        $this->assertEquals('Updated Tax Report 2023', $worksheet->getTitle(), 'Worksheet should be renamed');
        $this->assertEquals(1, $spreadsheet->getSheetCount(), 'Should have only one sheet after removal');

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    /**
     * Test practical worksheet naming scenarios
     */
    public function test_practical_worksheet_naming()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        nlog("=== Practical Worksheet Naming Scenarios ===");

        // Scenario 1: Tax Report with multiple sheets
        $taxWorksheet = $spreadsheet->getActiveSheet();
        $taxWorksheet->setTitle('Tax Summary');

        // Add data to tax sheet
        $taxWorksheet->fromArray([
            ['Tax Period', 'Amount', 'Status'],
            ['Q1 2023', 5000.00, 'Paid'],
            ['Q2 2023', 7500.00, 'Pending'],
        ], null, 'A1');

        // Scenario 2: Create Invoice Details sheet
        $invoiceWorksheet = $spreadsheet->createSheet();
        $invoiceWorksheet->setTitle('Invoice Details');

        // Add data to invoice sheet
        $invoiceWorksheet->fromArray([
            ['Invoice #', 'Client', 'Amount', 'Date'],
            ['INV-001', 'Client A', 1500.00, '2023-01-15'],
            ['INV-002', 'Client B', 2300.00, '2023-01-20'],
        ], null, 'A1');

        // Scenario 3: Create Summary sheet
        $summaryWorksheet = $spreadsheet->createSheet();
        $summaryWorksheet->setTitle('Summary');

        // Add summary data
        $summaryWorksheet->fromArray([
            ['Report Type', 'Total Amount', 'Record Count'],
            ['Tax Summary', 12500.00, 2],
            ['Invoice Details', 3800.00, 2],
        ], null, 'A1');

        // Scenario 4: Set Summary as active sheet
        $spreadsheet->setActiveSheetIndexByName('Summary');
        $activeSheet = $spreadsheet->getActiveSheet();
        nlog("Active sheet is now: " . $activeSheet->getTitle());

        // Scenario 5: List all sheets
        $allSheets = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $allSheets[] = $sheet->getTitle();
        }
        nlog("All sheets: " . implode(', ', $allSheets));

        // Test expectations
        $this->assertEquals(3, $spreadsheet->getSheetCount(), 'Should have 3 sheets');
        $this->assertEquals('Summary', $spreadsheet->getActiveSheet()->getTitle(), 'Summary should be active');

        // Save the test file
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $outputFile = storage_path('app/test_worksheet_naming.xlsx');
        $writer->save($outputFile);

        $this->assertFileExists($outputFile, 'Worksheet naming test file should be created');

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    /**
     * Validate worksheet name (Excel has restrictions)
     */
    private function validateWorksheetName($name)
    {
        // Excel worksheet name restrictions
        $restrictedChars = ['\\', '/', '*', '?', ':', '[', ']'];
        $maxLength = 31;

        // Check for restricted characters
        foreach ($restrictedChars as $char) {
            if (strpos($name, $char) !== false) {
                return false;
            }
        }

        // Check length
        if (strlen($name) > $maxLength) {
            return false;
        }

        // Check if name is empty
        if (empty(trim($name))) {
            return false;
        }

        return true;
    }
}
