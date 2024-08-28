<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace Tests\Feature\PhpOffice;

use Tests\TestCase;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CsvConversionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testExample()
    {

        $spreadsheet = new Spreadsheet();
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();

        /* Set CSV parsing options */

        $reader->setDelimiter(',');
        // $reader->setEnclosure('"');
        $reader->setSheetIndex(0);

        /* Load a CSV file and save as a XLS */

        $spreadsheet = $reader->load(base_path().'/tests/Feature/Import/expenses.csv');
        $writer = new Xlsx($spreadsheet);
        $writer->save(storage_path('/test.xlsx'));

        $spreadsheet->disconnectWorksheets();

        $this->assertTrue(file_exists(storage_path('/test.xlsx')));
        unlink(storage_path('/test.xlsx'));


    }
}
