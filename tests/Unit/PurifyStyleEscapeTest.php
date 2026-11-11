<?php

namespace Tests\Unit;

use App\Services\ClientPortal\PortalHtmlSanitizer;
use App\Services\Pdf\Purify;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PurifyStyleEscapeTest extends TestCase
{
    #[DataProvider('escapedMarkup')]
    public function test_css_normalization_cannot_create_html_elements(string $css, string $entryPoint): void
    {
        config(['ninja.environment' => 'hosted', 'ninja.disable_purify_html' => false]);

        $input = '<p>Before</p><style>' . $css . '</style><p>After</p>';
        $clean = match ($entryPoint) {
            'pdf' => Purify::clean($input),
            'fragment' => Purify::cleanUntrustedDocument($input, true),
            'portal' => (new PortalHtmlSanitizer())->clean($input),
        };

        $this->assertNoInjectedElements($clean);

        // Saving and rendering sanitize the same content more than once.
        $this->assertNoInjectedElements((new PortalHtmlSanitizer())->clean($clean));
    }

    public static function escapedMarkup(): array
    {
        $payloads = [
            'short hex image handler' => '\3c /style\3e \3c img src=x onerror="window.portalProbe=1"\3e ',
            'six digit hex image handler' => '\00003c/style\00003e\00003cimg src=x onerror="window.portalProbe=1"\00003e',
            'mixed case closing tag' => '\3C /StYlE\3E \3C img src=x onerror="window.portalProbe=1"\3E ',
            'escaped slash after literal less-than' => '<\2f style><img src=x onerror="window.portalProbe=1">',
            'script element' => '\3c /style\3e \3c script\3e window.portalProbe=1;\3c /script\3e ',
            'split tag' => '\</st\</yle><img src=x onerror="window.portalProbe=1">',
            'comment removal' => '</st/**/yle><img src=x onerror="window.portalProbe=1">',
            'escaped letter' => '</st\79 le><img src=x onerror="window.portalProbe=1">',
            'protocol removal' => '</sthttp://yle><img src=x onerror="window.portalProbe=1">',
            'slash delimiter' => '\3c /style/><img src=x onerror="window.portalProbe=1">',
        ];

        $cases = [];
        foreach ($payloads as $name => $css) {
            foreach (['pdf', 'fragment', 'portal'] as $entryPoint) {
                $cases[$name . ' / ' . $entryPoint] = [$css, $entryPoint];
            }
        }

        return $cases;
    }

    private function assertNoInjectedElements(string $html): void
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $this->assertTrue($document->loadHTML($html, LIBXML_NONET));
            $this->assertSame(0, $document->getElementsByTagName('img')->length, 'CSS escaped its style element: ' . $html);
            $this->assertSame(0, $document->getElementsByTagName('script')->length, 'CSS introduced a script element: ' . $html);
            $this->assertSame(1, $document->getElementsByTagName('style')->length);
            $this->assertSame(2, $document->getElementsByTagName('p')->length);
            $this->assertSame('Before', $document->getElementsByTagName('p')->item(0)->textContent);
            $this->assertSame('After', $document->getElementsByTagName('p')->item(1)->textContent);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
