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
 * The paid/cancelled stamp is injected directly into the JSON design template
 * (no $-variable substitution): visibility is decided in PHP from the already
 * resolved $show_paid_stamp gate, and the overlay anchors over the totals
 * block, falling back to the line-items table.
 */
class JsonDesignPaidStampTest extends TestCase
{
    private const PAID_LOGO = '<div class="stamp is-paid">PAID</div>';

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
        $design = ['documentSettings' => ['showPaidStamp' => true], 'blocks' => $blocks];

        $service = $this->service();
        $this->setProp($service, 'pdfService', $ps);
        $this->setProp($service, 'jsonDesign', $design);
        $this->setProp($service, 'adapter', new JsonToSectionsAdapter($design, $ps));

        return $this->invoke($service, 'generateBaseTemplate');
    }

    private function block(string $id, string $type, int $y): array
    {
        return [
            'id' => $id,
            'type' => $type,
            'gridPosition' => ['x' => 0, 'y' => $y, 'w' => 12, 'h' => 3],
            'properties' => ['columns' => [], 'items' => []],
        ];
    }

    /* -------------------- overlay visibility -------------------- */

    public function testPaidStampOverlayShownWhenStampFlagIsFlex(): void
    {
        $service = $this->service();
        $this->setProp($service, 'pdfService', $this->pdfService([
            '$show_paid_stamp' => 'flex',
            '$status_logo' => self::PAID_LOGO,
        ]));

        $html = $this->invoke($service, 'paidStampOverlay');
        $this->assertStringContainsString('class="stamp-overlay"', $html);
        $this->assertStringContainsString('PAID', $html);
    }

    public function testPaidStampOverlayHiddenWhenStampFlagIsNone(): void
    {
        $service = $this->service();
        $this->setProp($service, 'pdfService', $this->pdfService([
            '$show_paid_stamp' => 'none',
            '$status_logo' => self::PAID_LOGO,
        ]));

        $this->assertSame('', $this->invoke($service, 'paidStampOverlay'));
    }

    /* -------------------- anchor block resolution -------------------- */

    public function testStampTargetPrefersTotalsBlock(): void
    {
        $id = $this->invoke($this->service(), 'stampTargetBlockId', [
            ['id' => 'items', 'type' => 'table'],
            ['id' => 'totals', 'type' => 'total'],
        ]);

        $this->assertSame('totals', $id);
    }

    public function testStampTargetFallsBackToTableBlock(): void
    {
        $id = $this->invoke($this->service(), 'stampTargetBlockId', [
            ['id' => 'notes', 'type' => 'text'],
            ['id' => 'items', 'type' => 'table'],
        ]);

        $this->assertSame('items', $id);
    }

    public function testStampTargetNullWhenNoTotalsOrTable(): void
    {
        $this->assertNull($this->invoke($this->service(), 'stampTargetBlockId', [
            ['id' => 'logo', 'type' => 'image'],
        ]));
    }

    /* -------------------- end-to-end template injection -------------------- */

    public function testOverlayInjectedIntoTotalsBlockWhenPaid(): void
    {
        $html = $this->generate(
            ['$show_paid_stamp' => 'flex', '$status_logo' => self::PAID_LOGO],
            [$this->block('items', 'table', 0), $this->block('totals', 'total', 5)],
        );

        $this->assertStringContainsString('.stamp-overlay {', $html);

        preg_match('/<div id="totals"[^>]*style="([^"]*)"[^>]*>(.*?)\n/s', $html, $totals);
        $this->assertStringContainsString('position: relative', $totals[1]);
        $this->assertStringContainsString('stamp-overlay', $totals[2]);

        preg_match('/<div id="items"[^>]*>(.*?)\n/s', $html, $items);
        $this->assertStringNotContainsString('stamp-overlay', $items[1]);
    }

    public function testOverlayFallsBackToTableWhenNoTotalsBlock(): void
    {
        $html = $this->generate(
            ['$show_paid_stamp' => 'flex', '$status_logo' => self::PAID_LOGO],
            [$this->block('items', 'table', 0)],
        );

        preg_match('/<div id="items"[^>]*>(.*?)\n/s', $html, $items);
        $this->assertStringContainsString('stamp-overlay', $items[1]);
    }

    public function testNoOverlayWhenNotPaid(): void
    {
        $html = $this->generate(
            ['$show_paid_stamp' => 'none', '$status_logo' => self::PAID_LOGO],
            [$this->block('items', 'table', 0), $this->block('totals', 'total', 5)],
        );

        $this->assertStringNotContainsString('class="stamp-overlay"', $html);
    }
}
