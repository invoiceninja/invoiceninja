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

namespace Tests\Feature\Export;

use App\Models\Invoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class ExportCsvTest extends TestCase
{
    use MakesHash;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->makeTestData();

        $this->withoutExceptionHandling();
    }

    public function testExportCsv()
    {
        $csv = Writer::createFromFileObject(new \SplTempFileObject());

        $header_invoice = Invoice::take(10)->get()->toArray();
        $header_item = $header_invoice[0]['line_items'][0];
        unset($header_invoice[0]['line_items']);

        $header_invoice_keys = array_keys($header_invoice[0]);
        $header_item_keys = array_keys((array) $header_item);

        $header_invoice_values = array_values($header_invoice[0]);
        $header_item_values = array_values((array) $header_item);

        $merged_values = array_merge($header_invoice_values, (array) $header_item_values);
        $merged_keys = array_merge($header_invoice_keys, (array) $header_item_keys);

        // nlog(print_r($merged_keys, 1));
        // nlog(print_r($merged_values, 1));

        foreach ($merged_keys as &$key) {
            $key = ctrans('texts.'.$key);
        }

        $csv->insertOne($merged_keys);

        foreach (Invoice::take(10)->get() as $invoice) {
            foreach ($invoice->line_items as $item) {
                unset($invoice->line_items);

                $csv->insertOne(array_merge($invoice->toArray(), (array) $item));
            }
        }

        // Storage::put('invy.csv', $csv->getContent());

        $this->markTestSkipped();
    }
}
