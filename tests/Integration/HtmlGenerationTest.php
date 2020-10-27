<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace Tests\Integration;

use App\Designs\Bold;
use App\Designs\Designer;
use App\Models\Credit;
use App\Models\Design;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Utils\HtmlEngine;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesInvoiceHtml;
use Tests\MockAccountData;
use Tests\TestCase;
use App\Services\PdfMaker\Design as PdfDesignModel;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Services\PdfMaker\PdfMaker as PdfMakerService;
/**
 * @test
 */
class HtmlGenerationTest extends TestCase
{
    use MockAccountData;
    use MakesHash;

    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testHtmlOutput()
    {
        $html = $this->generateHtml($this->invoice);

        $this->assertNotNull($html);
    }

    private function generateHtml($entity)
    {
        $entity_design_id = '';

        if($entity instanceof Invoice || $entity instanceof RecurringInvoice){
            $entity_design_id = 'invoice_design_id';
        }
        elseif($entity instanceof Quote){
            $entity_design_id = 'quote_design_id';
        }
        elseif($entity instanceof Credit){
            $entity_design_id = 'credit_design_id';
        }

        $entity_design_id = $entity->design_id ? $entity->design_id : $this->decodePrimaryKey($entity->client->getSetting($entity_design_id));

        $design = Design::find($entity_design_id);
        $html = new HtmlEngine($entity->invitations->first());

        if ($design->is_custom) {
          $options = [
            'custom_partials' => json_decode(json_encode($design->design), true)
          ];
          $template = new PdfMakerDesign(PdfDesignModel::CUSTOM, $options);
        } else {
          $template = new PdfMakerDesign(strtolower($design->name));
        }

        $state = [
            'template' => $template->elements([
                'client' => $entity->client,
                'entity' => $entity,
                'pdf_variables' => (array) $entity->company->settings->pdf_variables,
                'products' => $design->design->product,
            ]),
            'variables' => $html->generateLabelsAndValues(),
            'options' => [
                'all_pages_header' => $entity->client->getSetting('all_pages_header'),
                'all_pages_footer' => $entity->client->getSetting('all_pages_footer'),
            ],
        ];

        $maker = new PdfMakerService($state);

        return $maker->design($template)
                     ->build()
                     ->getCompiledHTML(true);

    }
}
