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

use Tests\TestCase;
use App\Services\Pdf\PdfService;
use App\Services\Pdf\PdfConfiguration;
use App\Services\Pdf\JsonDesignService;
use App\Services\Pdf\JsonToSectionsAdapter;

/**
 * $entity_images is injected into the JSON design template when HtmlEngine
 * produced markup and the design does not already include that variable.
 */
class JsonDesignEntityImagesTest extends TestCase
{
    private const ENTITY_IMAGES = '<div class="entity-images">IMG</div>';

    private function reflect(): \ReflectionClass
    {
        return new \ReflectionClass(JsonDesignService::class);
    }

    private function service(): JsonDesignService
    {
        return $this->reflect()->newInstanceWithoutConstructor();
    }

    private function pdfService(array $values): PdfService
    {
        $ps = (new \ReflectionClass(PdfService::class))->newInstanceWithoutConstructor();
        $ps->html_variables = ['values' => $values, 'labels' => []];

        $cfg = (new \ReflectionClass(PdfConfiguration::class))->newInstanceWithoutConstructor();
        $settings = (new \ReflectionClass(PdfConfiguration::class))->getProperty('settings');
        $settings->setAccessible(true);
        $settings->setValue($cfg, (object) []);

        $config = (new \ReflectionClass(PdfService::class))->getProperty('config');
        $config->setAccessible(true);
        $config->setValue($ps, $cfg);

        return $ps;
    }

    private function setProp(object $obj, string $prop, $value): void
    {
        $p = (new \ReflectionClass($obj))->getProperty($prop);
        $p->setAccessible(true);
        $p->setValue($obj, $value);
    }

    private function invoke(JsonDesignService $service, string $method, ...$args)
    {
        $m = $this->reflect()->getMethod($method);
        $m->setAccessible(true);

        return $m->invoke($service, ...$args);
    }

    private function generate(array $values, array $blocks): string
    {
        $ps = $this->pdfService($values);
        $design = ['blocks' => $blocks];

        $service = $this->service();
        $this->setProp($service, 'pdfService', $ps);
        $this->setProp($service, 'jsonDesign', $design);
        $this->setProp($service, 'adapter', new JsonToSectionsAdapter($design, $ps));

        return $this->invoke($service, 'generateBaseTemplate');
    }

    private function block(string $id, string $type, int $y, array $properties = []): array
    {
        return [
            'id' => $id,
            'type' => $type,
            'gridPosition' => ['x' => 0, 'y' => $y, 'w' => 12, 'h' => 3],
            'properties' => $properties,
        ];
    }

    public function testEntityImagesInjectedWhenMarkupIsPresent(): void
    {
        $html = $this->generate(
            ['$entity_images' => self::ENTITY_IMAGES],
            [$this->block('notes', 'text', 0, ['content' => 'Hello'])],
        );

        $this->assertStringContainsString('id="entity-images"', $html);
        $this->assertStringContainsString(self::ENTITY_IMAGES, $html);
    }

    public function testEntityImagesOmittedWhenMarkupIsEmpty(): void
    {
        $html = $this->generate(
            ['$entity_images' => ''],
            [$this->block('notes', 'text', 0, ['content' => 'Hello'])],
        );

        $this->assertStringNotContainsString('id="entity-images"', $html);
    }

    public function testEntityImagesNotDuplicatedWhenDesignAlreadyIncludesVariable(): void
    {
        $html = $this->generate(
            ['$entity_images' => self::ENTITY_IMAGES],
            [$this->block('images', 'text', 0, ['content' => '$entity_images'])],
        );

        $this->assertStringNotContainsString('id="entity-images"', $html);
    }
}
