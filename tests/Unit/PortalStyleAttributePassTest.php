<?php

namespace Tests\Unit;

use App\Services\Pdf\Purify;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Services\ClientPortal\PortalHtmlSanitizer;
use Tests\TestCase;

class PortalStyleAttributePassTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ninja.environment' => 'hosted']);
    }

    #[DataProvider('nonHostedEnvironments')]
    public function test_non_hosted_content_and_settings_are_unchanged(?string $environment): void
    {
        config(['ninja.environment' => $environment]);
        $html = '<style media="print" id="theme">@media (width < 1000px) { p { color: red; } }</style>'
            . '<a href="mailto:billing@example.com">Contact</a><script>window.portalProbe = true;</script>';
        $settings = ['portal_custom_head' => $html, 'portal_custom_footer' => $html, 'invoice_terms' => null];
        $sanitizer = new PortalHtmlSanitizer();

        $this->assertSame($html, $sanitizer->clean($html));
        $this->assertSame($settings, $sanitizer->cleanSettings($settings));
    }

    public static function nonHostedEnvironments(): array
    {
        return [['selfhost'], ['other'], [null]];
    }

    #[DataProvider('fragments')]
    public function test_second_pass_removes_attributes_and_preserves_fragment(string $input, string $expected): void
    {
        // Bypass Purify here: otherwise its existing fix would mask a broken second pass.
        $sanitizer = new PortalHtmlSanitizer();

        $this->assertSame($expected, $sanitizer->stripStyleAttributes($input));
        $this->assertSame($expected, $sanitizer->stripStyleAttributes($expected));
    }

    public static function fragments(): array
    {
        $css = '@import url("data:text/css,body%7B--portal-xss-probe%3A1%7D");'
            . '[data-portal="client"] { --portal-primary: #176b55; }';

        return [
            'empty' => ['', ''],
            'original exploit' => [
                '<style onload="window.portalProbe = true">' . $css . '</style>',
                '<style>' . $css . '</style>',
            ],
            'all attributes and mixed case' => [
                '<STYLE OnLoAd="alert(1)" onerror="alert(2)" id="theme" class="brand" type="text/css" media="print" nonce="example" data-theme="green">p { color: green; }</STYLE>',
                '<style>p { color: green; }</style>',
            ],
            'siblings and nested styles' => [
                'Before<style id="one">p { color: green; }</style><div class="brand">Middle<style id="two">a { color: blue; }</style></div>After',
                'Before<style>p { color: green; }</style><div class="brand">Middle<style>a { color: blue; }</style></div>After',
            ],
            'unicode entities comments and links' => [
                '<!-- header --><p>Café 日本語 &amp; billing</p><a href="https://example.com/help">Help</a>',
                '<!-- header --><p>Café 日本語 &amp; billing</p><a href="https://example.com/help">Help</a>',
            ],
            'css raw text' => [
                '<style id="theme">p::before { content: "<div> & text"; } /* keep */</style>',
                '<style>p::before { content: "<div> & text"; } /* keep */</style>',
            ],
            'table' => [
                '<table><tr><td>Payment details</td></tr></table><style id="theme">td { color: green; }</style>',
                '<table><tr><td>Payment details</td></tr></table><style>td { color: green; }</style>',
            ],
        ];
    }

    public function test_full_pipeline_is_not_disabled_by_configuration(): void
    {
        config(['ninja.disable_purify_html' => true]);

        $input = '<p onclick="alert(1)">Welcome</p><script>alert(2)</script>'
            . '<style onload="alert(3)">p { color: green; }</style>';

        $this->assertSame(
            '<p>Welcome</p><style>p { color: green; }</style>',
            (new PortalHtmlSanitizer())->clean($input)
        );
    }

    public function test_second_pass_preserves_repaired_html_from_first_pass(): void
    {
        $input = '<p>Welcome <strong>client</p><p>After</p>'
            . '<style id="theme">p { color: green; }</style>';
        $firstPass = Purify::cleanUntrustedDocument($input, true);
        $result = (new PortalHtmlSanitizer())->stripStyleAttributes($firstPass);

        $this->assertStringNotContainsString('id="theme"', $result);
        $this->assertStringContainsString('Welcome', $result);
        $this->assertStringContainsString('After', $result);
        $this->assertStringContainsString('p { color: green; }', $result);
    }

    public function test_malformed_wrapper_closure_preserves_and_sanitizes_trailing_content(): void
    {
        $input = '<p>Before</p></div><p onclick="alert(1)">After</p>'
            . '<style onload="alert(2)" id="theme">p { color: green; }</style>';

        $this->assertSame(
            '<p>Before</p><p>After</p><style>p { color: green; }</style>',
            (new PortalHtmlSanitizer())->clean($input)
        );
    }

    public function test_settings_preserve_inheritance_and_unrelated_fields(): void
    {
        $settings = [
            'portal_custom_head' => '<style id="theme" onload="alert(1)">p { color: green; }</style>',
            'portal_custom_footer' => null,
            'invoice_terms' => '<p>Invoice terms</p>',
        ];

        $clean = (new PortalHtmlSanitizer())->cleanSettings($settings);

        $this->assertSame('<style>p { color: green; }</style>', $clean['portal_custom_head']);
        $this->assertNull($clean['portal_custom_footer']);
        $this->assertSame($settings['invoice_terms'], $clean['invoice_terms']);
        $this->assertSame([], (new PortalHtmlSanitizer())->cleanSettings([]));
    }

    public function test_second_pass_restores_libxml_error_mode(): void
    {
        $previous = libxml_use_internal_errors(false);

        try {
            (new PortalHtmlSanitizer())->stripStyleAttributes('<style id="theme">p { color: green; }</style>');
            $this->assertFalse(libxml_use_internal_errors());
        } finally {
            libxml_use_internal_errors($previous);
        }
    }
}
