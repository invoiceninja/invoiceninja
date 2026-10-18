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

namespace Tests\Pdf;

use App\DataMapper\CompanySettings;
use App\Models\Account;
use App\Models\Company;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Design;
use App\Models\Invoice;
use App\Services\Pdf\PdfBuilder;
use App\Services\Pdf\PdfConfiguration;
use App\Services\Pdf\PdfMock;
use App\Services\Pdf\PdfService;
use Tests\TestCase;

/**
 *
 *   App\Services\Pdf\PdfService
 */
class PdfmockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

    }

    public function testPdfInstance()
    {
        $data = [
            'settings' => CompanySettings::defaults(),
            'settings_type' => 'company',
            'entity_type' => 'invoice',
        ];

        $company = Company::factory()->make();
        $account = Account::factory()->make();
        $company->setRelation('account', $account);

        $entity = (new \App\Services\Pdf\PdfMock($data, $company))->build()->initEntity();

        $this->assertInstanceOf(Invoice::class, $entity);
        $this->assertNotNull($entity->client);

        $pdf_service = new PdfService($entity->invitation);

        $this->assertNotNull($pdf_service);

        $pdf_config = (new PdfConfiguration($pdf_service));

        $this->assertNotNull($pdf_config);


    }

    public function testHtmlGeneration()
    {
        $data = [
            'settings' => CompanySettings::defaults(),
            'settings_type' => 'company',
            'entity_type' => 'invoice',
        ];

        $company = Company::factory()->make();
        $account = Account::factory()->make();
        $company->setRelation('account', $account);

        $pdf_mock = (new PdfMock($data, $company))->build();
        $mock = $pdf_mock->initEntity();

        $pdf_service = new PdfService($mock->invitation);

        $pdf_config = (new PdfConfiguration($pdf_service));
        $pdf_config->entity = $mock;
        $pdf_config->setTaxMap($mock->tax_map);
        $pdf_config->setTotalTaxMap($mock->total_tax_map);
        $pdf_config->setCurrency(Currency::find(1));
        $pdf_config->setCountry(Country::find(840));
        $pdf_config->client = $mock->client;
        $pdf_config->entity_design_id = 'invoice_design_id';
        $pdf_config->settings_object = $mock->client;
        $pdf_config->entity_string = 'invoice';
        $pdf_config->settings = $pdf_mock->getMergedSettings();
        $pdf_mock->settings = $pdf_config->settings;
        $pdf_config->setPdfVariables();
        $pdf_config->design = Design::find(2);
        $pdf_config->currency_entity = $mock->client;

        $pdf_service->config = $pdf_config;

        $pdf_designer = (new \App\Services\Pdf\PdfDesigner($pdf_service))->build();
        $pdf_service->designer = $pdf_designer;

        $pdf_service->html_variables = $pdf_mock->getStubVariables();

        $pdf_builder = (new PdfBuilder($pdf_service))->build();
        $pdf_service->builder = $pdf_builder;
        $this->assertNotNull($pdf_config);

        $line_items = $mock->line_items;
        $line_items[0]->tags = 'Retail,Priority Customer';
        $transformed_items = $pdf_builder->transformLineItems($line_items);

        $this->assertSame('Retail · Priority Customer', $transformed_items[0]['$product.tags']);

        $html = $pdf_service->getHtml();

        $this->assertNotNull($html);
    }

    public function testTagVariablesHaveMatchingLabelsInLengthOrder(): void
    {
        $data = [
            'settings' => CompanySettings::defaults(),
            'settings_type' => 'company',
            'entity_type' => 'invoice',
        ];

        $company = Company::factory()->make();
        $account = Account::factory()->make();
        $company->setRelation('account', $account);

        $variables = (new PdfMock($data, $company))->build()->getStubVariables();
        $expectedTagValueKeys = [
            '$purchase_order.tags',
            '$product.tags',
            '$invoice.tags',
            '$company.tags',
            '$client.tags',
            '$vendor.tags',
            '$credit.tags',
            '$quote.tags',
            '$task.tags',
        ];
        $expectedTagLabelKeys = array_map(
            fn (string $key): string => "{$key}_label",
            $expectedTagValueKeys,
        );
        $tagLabels = array_intersect_key($variables['labels'], array_flip($expectedTagLabelKeys));
        $tagLabelKeyLengths = array_map(strlen(...), array_keys($tagLabels));
        $sortedTagLabelKeyLengths = $tagLabelKeyLengths;

        rsort($sortedTagLabelKeyLengths);

        $this->assertSame(
            $expectedTagValueKeys,
            array_keys(array_intersect_key($variables['values'], array_flip($expectedTagValueKeys))),
        );
        $this->assertSame($expectedTagLabelKeys, array_keys($tagLabels));
        $this->assertSame($sortedTagLabelKeyLengths, $tagLabelKeyLengths);
        $this->assertSame(
            array_fill_keys($expectedTagLabelKeys, ctrans('texts.tags')),
            $tagLabels,
        );
        $this->assertArrayNotHasKey('$tasks.tags_label', $variables['labels']);
    }

    public function testPurchaseOrderTagVariablesHaveMatchingLabelsInLengthOrder(): void
    {
        $data = [
            'settings' => CompanySettings::defaults(),
            'settings_type' => 'company',
            'entity_type' => 'purchase_order',
        ];

        $company = Company::factory()->make();
        $account = Account::factory()->make();
        $company->setRelation('account', $account);

        $pdfMock = (new PdfMock($data, $company))->build();
        $pdfService = (new \ReflectionProperty(PdfMock::class, 'pdf_service'))->getValue($pdfMock);
        $variables = $pdfService->html_variables;
        $expectedTagValueKeys = [
            '$purchase_order.tags',
            '$vendor.tags',
        ];
        $expectedTagLabelKeys = array_map(
            fn (string $key): string => "{$key}_label",
            $expectedTagValueKeys,
        );
        $tagLabels = array_intersect_key($variables['labels'], array_flip($expectedTagLabelKeys));
        $tagLabelKeyLengths = array_map(strlen(...), array_keys($tagLabels));
        $sortedTagLabelKeyLengths = $tagLabelKeyLengths;

        rsort($sortedTagLabelKeyLengths);

        $this->assertSame(
            $expectedTagValueKeys,
            array_keys(array_intersect_key($variables['values'], array_flip($expectedTagValueKeys))),
        );
        $this->assertSame($expectedTagLabelKeys, array_keys($tagLabels));
        $this->assertSame($sortedTagLabelKeyLengths, $tagLabelKeyLengths);
        $this->assertSame(
            array_fill_keys($expectedTagLabelKeys, ctrans('texts.tags')),
            $tagLabels,
        );
    }

}
