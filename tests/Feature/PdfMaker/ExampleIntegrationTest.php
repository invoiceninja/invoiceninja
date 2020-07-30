<?php

namespace Tests\Feature\PdfMaker;

use App\Models\Design;
use App\Models\Invoice;
use App\Services\PdfMaker\Designs\Plain;
use App\Services\PdfMaker\PdfMaker;
use App\Utils\HtmlEngine;
use Tests\TestCase;

class ExampleIntegrationTest extends TestCase
{
    public function testExample()
    {
        $invoice = Invoice::first();
        $invitation = $invoice->invitations()->first();

        $engine = new HtmlEngine($invitation, 'invoice');
        $design = new Plain();
        

        $state = [
            'template' => $design->elements(json_decode(json_encode($invoice->company->settings->pdf_variables), 1)['product_columns']),
            'variables' => $engine->generateLabelsAndValues(),
        ];

        $maker = new PdfMaker($state);

        $maker
            ->design(Plain::class)
            ->build();

        info($maker->getCompiledHTML());
    }
}
