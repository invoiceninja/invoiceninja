<?php

namespace Tests\Unit;

use App\Services\Pdf\Purify;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PurifyStyleAttributesTest extends TestCase
{
    #[DataProvider('styleAttributesProvider')]
    public function test_style_event_handlers_are_removed_while_theme_css_is_preserved(string $attributes): void
    {
        config(['ninja.disable_purify_html' => false]);

        $css = '[data-portal="client"] { --portal-primary: #176b55; --portal-button-radius: 8px; }';
        $html = '<STYLE ' . $attributes . '>' . $css . '</STYLE>';

        $this->assertSame('<style>' . $css . '</style>', Purify::clean($html, true));
        $this->assertSame('<style>' . $css . '</style>', Purify::cleanUntrustedDocument($html, true));
    }

    public static function styleAttributesProvider(): array
    {
        return [
            'event handlers' => ['onload="window.portalProbe = true" onerror="window.portalProbe = true"'],
            'mixed case event handler' => ['OnLoAd="window.portalProbe = true"'],
        ];
    }

    public function test_native_design_style_identifiers_are_preserved(): void
    {
        config(['ninja.disable_purify_html' => false]);

        $this->assertSame(
            '<style id="style" data-invoice-custom-css="">p { color: green; }</style>',
            Purify::clean('<style id="style" data-invoice-custom-css onload="alert(1)">p { color: green; }</style>', true)
        );

        foreach (glob(resource_path('views/pdf-designs/*.html')) as $file) {
            $extractor = (new \App\Services\Pdf\DesignExtractor())->setHtml(file_get_contents($file));
            $style = $extractor->getSectionHTML('style');

            if ($style === '') {
                continue;
            }

            $clean = Purify::clean($style, true);
            $this->assertStringContainsString('id="style"', $clean, basename($file));
            $this->assertNotEmpty((new \App\Services\Pdf\DesignExtractor())->setHtml($clean)->getSectionHTML('style'));
        }
    }

    public function test_pdf_style_media_is_preserved_but_portal_attributes_are_removed(): void
    {
        config(['ninja.environment' => 'hosted', 'ninja.disable_purify_html' => false]);
        $html = '<style media="print" type="text/css" onload="alert(1)">p { color: green; }</style>';

        $this->assertSame(
            '<style media="print" type="text/css">p { color: green; }</style>',
            Purify::clean($html, true)
        );
        $this->assertSame(
            '<style>p { color: green; }</style>',
            (new \App\Services\ClientPortal\PortalHtmlSanitizer())->clean($html)
        );
    }

    public function test_style_content_filtering_and_other_element_attributes_are_preserved(): void
    {
        config(['ninja.disable_purify_html' => false]);

        $html = '<style onload="window.portalProbe = true">'
            . '.invoiceninja-whitelabel { display: none; }'
            . '.brand { color: green; }'
            . '</style><div class="brand" onclick="window.portalProbe = true">Welcome</div>';

        $this->assertSame(
            '<style>.brand { color: green; }</style><div class="brand">Welcome</div>',
            Purify::clean($html, true)
        );
    }
}
