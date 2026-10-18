<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Pdf\PdfService;
use App\Services\Pdf\PdfConfiguration;
use App\Services\Pdf\JsonDesignService;
use App\Services\Pdf\JsonToSectionsAdapter;

class JsonDesignCustomCssTest extends TestCase
{
    private function generate(array $design): string
    {
        $pdfService = (new \ReflectionClass(PdfService::class))->newInstanceWithoutConstructor();
        $pdfService->html_variables = ['values' => [], 'labels' => []];

        $configuration = (new \ReflectionClass(PdfConfiguration::class))->newInstanceWithoutConstructor();
        $settings = (new \ReflectionClass(PdfConfiguration::class))->getProperty('settings');
        $settings->setAccessible(true);
        $settings->setValue($configuration, (object) []);

        $config = (new \ReflectionClass(PdfService::class))->getProperty('config');
        $config->setAccessible(true);
        $config->setValue($pdfService, $configuration);

        $reflection = new \ReflectionClass(JsonDesignService::class);
        $service = $reflection->newInstanceWithoutConstructor();

        foreach (['pdfService' => $pdfService, 'jsonDesign' => $design] as $property => $value) {
            $reflectedProperty = $reflection->getProperty($property);
            $reflectedProperty->setAccessible(true);
            $reflectedProperty->setValue($service, $value);
        }

        $adapter = $reflection->getProperty('adapter');
        $adapter->setAccessible(true);
        $adapter->setValue($service, new JsonToSectionsAdapter($design, $pdfService));

        $generate = $reflection->getMethod('generateBaseTemplate');
        $generate->setAccessible(true);

        return $generate->invoke($service);
    }

    private function block(string $id, string $type, int $x, int $y, int $width, array $properties = []): array
    {
        return [
            'id' => $id,
            'type' => $type,
            'gridPosition' => ['x' => $x, 'y' => $y, 'w' => $width, 'h' => 2],
            'properties' => $properties,
        ];
    }

    public function testTemplateEmitsStableWidgetClassesAndCustomCss(): void
    {
        $css = '.invoice-widget--table td { border: 0 !important; }';
        $html = $this->generate([
            'customCss' => $css,
            'blocks' => [
                $this->block(
                    'line-items',
                    'table',
                    0,
                    0,
                    12,
                    ['cssClasses' => 'compact highlighted compact bad.class invoice-widget--table']
                ),
                $this->block('notes', 'text', 0, 3, 6),
                $this->block('terms', 'terms', 6, 3, 6),
            ],
        ]);

        $this->assertStringContainsString(
            'id="line-items" class="json-block invoice-widget invoice-widget--table compact highlighted"',
            $html
        );
        $this->assertStringNotContainsString('bad.class', $html);
        $this->assertStringContainsString(
            'id="notes" class="invoice-widget invoice-widget--text"',
            $html
        );
        $this->assertStringContainsString(
            'id="terms" class="invoice-widget invoice-widget--terms"',
            $html
        );
        $this->assertStringContainsString($css, $html);
    }

    public function testCustomCssCannotCloseTheTemplateStyleElement(): void
    {
        $html = $this->generate([
            'customCss' => '</style><script>window.bad = true</script>',
            'blocks' => [$this->block('notes', 'text', 0, 0, 12)],
        ]);

        $this->assertStringNotContainsString('</style><script>', $html);
        $this->assertStringContainsString('<\\/style><script>', $html);
    }

    public function testApiStyleFragmentIsInjectedAsASeparateHeadElement(): void
    {
        $css = '.invoice-widget--text { color: rebeccapurple; }';
        $html = $this->generate([
            'customCss' => "<style>\n{$css}\n</style>",
            'blocks' => [$this->block('notes', 'text', 0, 0, 12)],
        ]);

        $customStyle = "<style data-invoice-custom-css>\n{$css}\n</style>";
        $headEnd = stripos($html, '</head>');
        $customStylePosition = strpos($html, $customStyle);

        $this->assertStringContainsString($customStyle, $html);
        $this->assertIsInt($headEnd);
        $this->assertIsInt($customStylePosition);
        $this->assertLessThan($headEnd, $customStylePosition);
        $this->assertSame(2, substr_count(strtolower($html), '<style'));
    }
}
