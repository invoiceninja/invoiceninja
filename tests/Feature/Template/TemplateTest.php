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

namespace Tests\Feature\Template;

use App\Models\Design;
use Tests\TestCase;
use App\Utils\Ninja;
use App\Utils\HtmlEngine;
use Tests\MockAccountData;
use App\Services\PdfMaker\PdfMaker;
use Illuminate\Support\Facades\App;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Services\PdfMaker\Design as PdfDesignModel;
use App\Services\PdfMaker\Design as PdfMakerDesign;

/**
 * @test
 * @covers 
 */
class TemplateTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
        
    }

    public function testTemplateGeneration()
    {
        $entity_obj = $this->invoice;
        
        $design = new Design();
        $design->design = json_decode(json_encode($this->invoice->company->settings->pdf_variables), true);
        $design->name = 'test';
        $design->is_active = true;
        $design->is_template = true;
        $design->is_custom = true;
        $design->user_id = $this->invoice->user_id;
        $design->company_id = $this->invoice->company_id;

            $design_object = new \stdClass;
            $design_object->includes = '';
            $design_object->header = '';
            $design_object->body = '';
            $design_object->product = '';
            $design_object->task = '';
            $design_object->footer = '';

            $design->design = $design_object;

        $design->save();

        App::forgetInstance('translator');
        $t = app('translator');
        App::setLocale($entity_obj->client->locale());
        $t->replace(Ninja::transformTranslations($entity_obj->client->getMergedSettings()));

        $html = new HtmlEngine($entity_obj->invitations()->first());

        /** @var \App\Models\Design $design */
        $design = \App\Models\Design::withTrashed()->find($entity_obj->design_id);

        $options = [
            'custom_partials' => json_decode(json_encode($design->design), true),
        ];
        $template = new PdfMakerDesign(PdfDesignModel::CUSTOM, $options);
    
        $variables = $html->generateLabelsAndValues();

        $state = [
            'template' => $template->elements([
                'client' => $entity_obj->client,
                'entity' => $entity_obj,
                'pdf_variables' => (array) $entity_obj->company->settings->pdf_variables,
                '$product' => $design->design->product,
                'variables' => $variables,
            ]),
            'variables' => $variables,
            'options' => [
                'all_pages_header' => $entity_obj->client->getSetting('all_pages_header'),
                'all_pages_footer' => $entity_obj->client->getSetting('all_pages_footer'),
                'client' => $entity_obj->client,
                'entity' => $entity_obj,
                'variables' => $variables,
            ],
            'process_markdown' => $entity_obj->client->company->markdown_enabled,
        ];

        $maker = new PdfMaker($state);


    }
}