<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\Models\Company;
use App\Services\Pdf\JsonDesignService;
use App\Services\Pdf\JsonToSectionsAdapter;
use App\Services\Pdf\PdfConfiguration;
use App\Services\Pdf\PdfService;
use Tests\TestCase;

class JsonDesignTwigBlockTest extends TestCase
{
    public function testTwigBlockEmitsNinjaElement(): void
    {
        $source = "{% for invoice in invoices %}\n  {{ invoice.number }}\n{% endfor %}";
        $sections = $this->sections([
            $this->twigBlock('twig-1', $source),
        ]);

        $this->assertArrayHasKey('twig-1', $sections);
        $this->assertSame('div', $sections['twig-1']['elements'][0]['element']);
        $this->assertSame('ninja', $sections['twig-1']['elements'][0]['elements'][0]['element']);
        $this->assertSame($source, $sections['twig-1']['elements'][0]['elements'][0]['content']);
    }

    public function testTwigBlockUnwrapsOuterNinjaTags(): void
    {
        $inner = "{% if invoices|length %}{{ invoices[0].number }}{% endif %}";
        $sections = $this->sections([
            $this->twigBlock('twig-wrap', "<ninja>\n{$inner}\n</ninja>"),
        ]);

        $this->assertSame("\n{$inner}\n", $sections['twig-wrap']['elements'][0]['elements'][0]['content']);
        $this->assertSame('ninja', $sections['twig-wrap']['elements'][0]['elements'][0]['element']);
    }

    public function testEmptyTwigBlockStillEmitsNinjaElement(): void
    {
        $sections = $this->sections([
            $this->twigBlock('twig-empty', ''),
        ]);

        $this->assertSame('ninja', $sections['twig-empty']['elements'][0]['elements'][0]['element']);
        $this->assertSame('', $sections['twig-empty']['elements'][0]['elements'][0]['content']);
    }

    public function testTwigBlockIsNotSplitLikeATextBlock(): void
    {
        $sections = $this->sections([
            $this->twigBlock('twig-lines', "line one\nline two"),
        ]);

        $this->assertCount(1, $sections['twig-lines']['elements']);
        $this->assertSame("line one\nline two", $sections['twig-lines']['elements'][0]['elements'][0]['content']);
    }

    public function testTemplatePlaceholderUsesTwigWidgetClass(): void
    {
        $html = $this->template([
            'blocks' => [$this->twigBlock('twig-class', '{{ invoice.number }}')],
        ]);

        $this->assertStringContainsString('id="twig-class"', $html);
        $this->assertStringContainsString('invoice-widget--twig', $html);
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @return array<string, mixed>
     */
    private function sections(array $blocks): array
    {
        return (new JsonToSectionsAdapter(
            ['pageSettings' => [], 'blocks' => $blocks],
            $this->minimalPdfService()
        ))->toSections();
    }

    /**
     * @param array<string, mixed> $design
     */
    private function template(array $design): string
    {
        $ps = $this->minimalPdfService();
        $service = (new \ReflectionClass(JsonDesignService::class))->newInstanceWithoutConstructor();

        $this->setProp($service, 'pdfService', $ps);
        $this->setProp($service, 'jsonDesign', $design);
        $this->setProp($service, 'adapter', new JsonToSectionsAdapter($design, $ps));

        $method = (new \ReflectionClass(JsonDesignService::class))->getMethod('generateBaseTemplate');
        $method->setAccessible(true);

        return $method->invoke($service);
    }

    /**
     * @return array<string, mixed>
     */
    private function twigBlock(string $id, string $content): array
    {
        return [
            'id' => $id,
            'type' => 'twig',
            'gridPosition' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 4],
            'properties' => ['content' => $content],
        ];
    }

    private function setProp(object $obj, string $prop, mixed $value): void
    {
        $p = new \ReflectionProperty($obj, $prop);
        $p->setAccessible(true);
        $p->setValue($obj, $value);
    }

    private function minimalPdfService(): PdfService
    {
        $service = (new \ReflectionClass(PdfService::class))->newInstanceWithoutConstructor();
        $company = new Company();
        $company->company_key = 'test-company';
        $service->company = $company;
        $service->html_variables = ['values' => [], 'labels' => []];

        $cfg = (new \ReflectionClass(PdfConfiguration::class))->newInstanceWithoutConstructor();
        $settings = new \ReflectionProperty($cfg, 'settings');
        $settings->setAccessible(true);
        $settings->setValue($cfg, (object) []);

        $config = new \ReflectionProperty($service, 'config');
        $config->setAccessible(true);
        $config->setValue($service, $cfg);

        return $service;
    }
}
