<?php

namespace Tests\Feature\PdfMaker;

use App\Models\Design;
use App\Models\Invoice;
use App\Services\PdfMaker\Designs\Plain;
use App\Services\PdfMaker\PdfMaker;
use App\Utils\HtmlEngine;
use App\Utils\Traits\MakesInvoiceValues;
use Tests\TestCase;

class ExampleIntegrationTest extends TestCase
{
    use MakesInvoiceValues;

    public function testExample()
    {
        $invoice = Invoice::first();
        $invitation = $invoice->invitations()->first();

        $engine = new HtmlEngine($invitation, 'invoice');
        $design = new Plain();

        $product_table_columns = json_decode(
            json_encode($invoice->company->settings->pdf_variables), 1
        )['product_columns'];

        $state = [
            'template' => $design->elements([
                'client' => $invoice->client,
                'invoice' => $invoice,
                'product-table-columns' => $product_table_columns,
            ]),
            'variables' => $engine->generateLabelsAndValues(),
        ];

        $maker = new PdfMaker($state);

        $maker
            ->design(Plain::class)
            ->build();

        info($maker->getCompiledHTML());
    }
}
