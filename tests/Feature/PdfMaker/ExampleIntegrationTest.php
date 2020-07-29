<?php

namespace Tests\Feature\PdfMaker;

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

        $state = [
            'template' => [
                
            ],
            'variables' => $engine->generateLabelsAndValues(),
        ];

        $maker = new PdfMaker($state);

        $maker
            ->design(Plain::class)
            ->build();

        info($state);
        info($maker->getCompiledHTML());
    }
}
