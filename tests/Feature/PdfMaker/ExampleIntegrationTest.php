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

namespace Tests\Feature\PdfMaker;

use App\Services\PdfMaker\Design;
use App\Services\PdfMaker\PdfMaker;
use App\Utils\HtmlEngine;
use App\Utils\Traits\MakesInvoiceValues;
use Tests\MockAccountData;
use Tests\TestCase;

class ExampleIntegrationTest extends TestCase
{
    use MakesInvoiceValues;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testExample()
    {
        // $this->markTestIncomplete();

        $invoice = $this->invoice;
        $invitation = $invoice->invitations()->first();

        $engine = new HtmlEngine($invitation);

        $design = new Design(
            Design::CLEAN
        );

        $state = [
            'template' => $design->elements([
                'client' => $invoice->client,
                'entity' => $invoice,
                'pdf_variables' => (array) $invoice->company->settings->pdf_variables,
            ]),
            'variables' => $engine->generateLabelsAndValues(),
             'options' => [
                'client' => $invoice->client,
                'invoices' => [$invoice]
            ],
        ];

        $maker = new PdfMaker($state);

        $maker
            ->design($design)
            ->build();

        $this->assertNotNull($maker->getCompiledHTML(true));
    }
}
