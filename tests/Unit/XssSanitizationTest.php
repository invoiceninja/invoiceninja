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

use App\Models\Product;
use App\Services\Pdf\Purify;
use App\Utils\Traits\CleanLineItems;
use Tests\TestCase;

class XssSanitizationTest extends TestCase
{
    use CleanLineItems;

    // =========================================================================
    // Vulnerability #2 — CleanLineItems: denylist bypass payloads
    // Each test uses an exact payload from the security report
    // =========================================================================

    public function test_svg_onload_is_stripped()
    {
        $result = $this->cleanItems([['notes' => '<svg onload=confirm(document.domain)>']]);

        $this->assertStringNotContainsString('onload', $result[0]['notes']);
        $this->assertStringNotContainsString('confirm(', $result[0]['notes']);
    }

    public function test_details_ontoggle_is_stripped()
    {
        $result = $this->cleanItems([['notes' => '<details open ontoggle=confirm(1)>']]);

        $this->assertStringNotContainsString('ontoggle', $result[0]['notes']);
        $this->assertStringNotContainsString('confirm(', $result[0]['notes']);
    }

    public function test_input_onfocus_is_stripped()
    {
        $result = $this->cleanItems([['notes' => '<input onfocus=confirm(1) autofocus>']]);

        $this->assertStringNotContainsString('onfocus', $result[0]['notes']);
        $this->assertStringNotContainsString('confirm(', $result[0]['notes']);
    }

    public function test_javascript_uri_is_stripped()
    {
        $result = $this->cleanItems([['notes' => '<a href="javascript:confirm(1)">Click</a>']]);

        $this->assertStringNotContainsString('javascript:', $result[0]['notes']);
    }

    public function test_onmouseover_is_stripped()
    {
        $result = $this->cleanItems([['notes' => '<img src=x onmouseover=alert(1)>']]);

        $this->assertStringNotContainsString('onmouseover', $result[0]['notes']);
        $this->assertStringNotContainsString('alert(', $result[0]['notes']);
    }

    public function test_marquee_onstart_is_stripped()
    {
        $result = $this->cleanItems([['notes' => '<marquee onstart=alert(1)>']]);

        $this->assertStringNotContainsString('onstart', $result[0]['notes']);
        $this->assertStringNotContainsString('alert(', $result[0]['notes']);
    }

    public function test_body_onpageshow_is_stripped()
    {
        $result = $this->cleanItems([['notes' => '<body onpageshow=alert(1)>']]);

        $this->assertStringNotContainsString('onpageshow', $result[0]['notes']);
        $this->assertStringNotContainsString('alert(', $result[0]['notes']);
    }

    public function test_all_report_bypass_payloads_are_neutralized()
    {
        $payloads = [
            '<svg onload=confirm(document.domain)>',
            '<details open ontoggle=confirm(1)>',
            '<input onfocus=confirm(1) autofocus>',
            '<a href="javascript:confirm(1)">Click</a>',
            '<img src=x onmouseover=alert(1)>',
        ];

        foreach ($payloads as $payload) {
            $result = $this->cleanItems([['notes' => $payload]]);
            $this->assertDoesNotMatchRegularExpression('/\bon\w+\s*=/i', $result[0]['notes'], "Event handler survived in: {$payload}");
            $this->assertStringNotContainsString('javascript:', $result[0]['notes'], "javascript: URI survived in: {$payload}");
        }
    }

    public function test_encoded_html_entity_xss_is_caught()
    {
        $result = $this->cleanItems([['notes' => '&lt;script&gt;alert(1)&lt;/script&gt;']]);

        $this->assertStringNotContainsString('<script>', $result[0]['notes']);
    }

    public function test_numeric_entity_xss_is_caught()
    {
        $result = $this->cleanItems([['notes' => '&#60;script&#62;alert(1)&#60;/script&#62;']]);

        $this->assertStringNotContainsString('<script>', $result[0]['notes']);
    }

    public function test_hex_entity_xss_is_caught()
    {
        $result = $this->cleanItems([['notes' => '&#x3C;script&#x3E;alert(1)&#x3C;/script&#x3E;']]);

        $this->assertStringNotContainsString('<script>', $result[0]['notes']);
    }

    public function test_safe_html_is_preserved_in_line_items()
    {
        $result = $this->cleanItems([['notes' => '<b>Bold</b> and <em>emphasis</em>']]);

        $this->assertStringContainsString('<b>', $result[0]['notes']);
        $this->assertStringContainsString('<em>', $result[0]['notes']);
        $this->assertStringContainsString('Bold', $result[0]['notes']);
        $this->assertStringContainsString('emphasis', $result[0]['notes']);
    }

    public function test_plain_text_is_untouched_in_line_items()
    {
        $text = 'This is a normal product description. Price: $49.99 (10% off)';
        $result = $this->cleanItems([['notes' => $text]]);

        $this->assertEquals($text, $result[0]['notes']);
    }

    public function test_all_six_fields_are_sanitized()
    {
        $xss = '<script>alert(1)</script>';
        $item = [
            'notes' => $xss,
            'product_key' => $xss,
            'custom_value1' => $xss,
            'custom_value2' => $xss,
            'custom_value3' => $xss,
            'custom_value4' => $xss,
        ];

        $result = $this->cleanItems([$item]);

        foreach (['notes', 'product_key', 'custom_value1', 'custom_value2', 'custom_value3', 'custom_value4'] as $field) {
            $this->assertStringNotContainsString('<script>', $result[0][$field], "Field {$field} was not sanitized");
            $this->assertStringNotContainsString('alert(', $result[0][$field], "Field {$field} still contains alert");
        }
    }

    public function test_mixed_safe_and_unsafe_html_in_line_items()
    {
        $result = $this->cleanItems([['notes' => '<b>Bold</b><script>alert(1)</script><em>Safe</em>']]);

        $this->assertStringContainsString('<b>', $result[0]['notes']);
        $this->assertStringContainsString('<em>', $result[0]['notes']);
        $this->assertStringNotContainsString('<script>', $result[0]['notes']);
        $this->assertStringNotContainsString('alert(', $result[0]['notes']);
    }

    // =========================================================================
    // Vulnerability #3 — Markdown HTML injection in Product notes
    // =========================================================================

    public function test_markdown_strips_script_tags()
    {
        $result = Product::markdownHelp("Features:\n\n<script>alert(document.cookie)</script>\n\n- Feature 1");

        $this->assertStringNotContainsString('<script>', (string) $result);
        $this->assertStringNotContainsString('alert(', (string) $result);
    }

    public function test_markdown_strips_img_onerror()
    {
        $result = Product::markdownHelp("Features:\n\n<img src=x onerror=alert(document.cookie)>\n\n- Feature 1");

        $this->assertStringNotContainsString('onerror', (string) $result);
    }

    public function test_markdown_strips_svg_onload()
    {
        $result = Product::markdownHelp("Notes:\n\n<svg onload=alert(1)></svg>");

        $this->assertStringNotContainsString('onload', (string) $result);
        $this->assertStringNotContainsString('alert(', (string) $result);
    }

    public function test_markdown_strips_event_handlers()
    {
        $payloads = [
            '<details open ontoggle=alert(1)>test</details>',
            '<input onfocus=alert(1) autofocus>',
            '<marquee onstart=alert(1)>',
            '<body onpageshow=alert(1)>',
            '<div onmouseover=alert(1)>hover</div>',
        ];

        foreach ($payloads as $payload) {
            $result = (string) Product::markdownHelp("Text\n\n{$payload}\n\nMore text");

            $this->assertDoesNotMatchRegularExpression('/\bon\w+\s*=/i', $result, "Event handler survived in: {$payload}");
        }
    }

    public function test_markdown_strips_javascript_uri()
    {
        $result = Product::markdownHelp('[Click me](javascript:alert(1))');

        $this->assertStringNotContainsString('javascript:', (string) $result);
    }

    public function test_markdown_preserves_valid_markdown()
    {
        $markdown = "## Heading\n\n**Bold text** and *italic*\n\n- Item 1\n- Item 2\n\nA [link](https://example.com)";
        $result = (string) Product::markdownHelp($markdown);

        $this->assertStringContainsString('Heading', $result);
        $this->assertStringContainsString('<strong>', $result);
        $this->assertStringContainsString('<em>', $result);
        $this->assertStringContainsString('<li>', $result);
        $this->assertStringContainsString('href', $result);
        $this->assertStringContainsString('example.com', $result);
    }

    public function test_markdown_handles_null_notes()
    {
        $result = Product::markdownHelp(null);

        $this->assertNotNull($result);
    }

    public function test_markdown_handles_empty_notes()
    {
        $result = Product::markdownHelp('');

        $this->assertNotNull($result);
    }

    // =========================================================================
    // Vulnerability #4 — Twig TemplateService hardening
    // =========================================================================

    public function test_twig_constant_not_in_allowlist()
    {
        $reflection = new \ReflectionClass(\App\Services\Template\TemplateService::class);
        $source = file_get_contents($reflection->getFileName());

        // Find the allowedFunctions array assignment
        preg_match('/\$allowedFunctions\s*=\s*\[([^\]]+)\]/', $source, $matches);
        $this->assertNotEmpty($matches, 'Could not find $allowedFunctions in TemplateService');

        $this->assertStringNotContainsString("'constant'", $matches[1], 'constant() should not be in the Twig sandbox allowlist');
    }

    public function test_twig_debug_not_hardcoded_true()
    {
        $reflection = new \ReflectionClass(\App\Services\Template\TemplateService::class);
        $source = file_get_contents($reflection->getFileName());

        // Ensure debug is not hardcoded to true
        $this->assertDoesNotMatchRegularExpression("/'debug'\s*=>\s*true/", $source, 'Twig debug should not be hardcoded to true');
    }

    // =========================================================================
    // Purify::clean() — core sanitizer tests
    // =========================================================================

    public function test_purify_strips_script_elements()
    {
        $result = Purify::clean('<p>Safe</p><script>alert(1)</script><p>Also safe</p>');

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('alert(', $result);
        $this->assertStringContainsString('Safe', $result);
        $this->assertStringContainsString('Also safe', $result);
    }

    public function test_purify_strips_onerror_from_img()
    {
        $result = Purify::clean('<img src="https://example.com/img.jpg" onerror="alert(1)" style="width:100px">');

        $this->assertStringNotContainsString('onerror', $result);
        $this->assertStringNotContainsString('alert(', $result);
    }

    public function test_purify_strips_all_event_handler_attributes()
    {
        $handlers = [
            '<div onclick="alert(1)">click</div>',
            '<div onmouseover="alert(1)">hover</div>',
            '<div onmouseenter="alert(1)">enter</div>',
            '<div onload="alert(1)">load</div>',
            '<div onfocus="alert(1)">focus</div>',
            '<div onblur="alert(1)">blur</div>',
            '<div onkeydown="alert(1)">key</div>',
            '<div onsubmit="alert(1)">submit</div>',
            '<div onanimationend="alert(1)">anim</div>',
            '<div onpointerdown="alert(1)">pointer</div>',
        ];

        foreach ($handlers as $payload) {
            $result = Purify::clean($payload);
            $this->assertDoesNotMatchRegularExpression('/\bon\w+\s*=/i', $result, "Event handler survived in: {$payload}");
        }
    }

    public function test_purify_preserves_safe_elements()
    {
        $html = '<div><p>Text</p><b>Bold</b><em>Italic</em><strong>Strong</strong><span>Span</span></div>';
        $result = Purify::clean($html);

        $this->assertStringContainsString('<div>', $result);
        $this->assertStringContainsString('<p>', $result);
        $this->assertStringContainsString('<b>', $result);
        $this->assertStringContainsString('<em>', $result);
        $this->assertStringContainsString('<strong>', $result);
        $this->assertStringContainsString('<span>', $result);
    }

    public function test_purify_preserves_safe_table_elements()
    {
        $html = '<table><thead><tr><th>Header</th></tr></thead><tbody><tr><td>Cell</td></tr></tbody></table>';
        $result = Purify::clean($html);

        $this->assertStringContainsString('<table>', $result);
        $this->assertStringContainsString('<th>', $result);
        $this->assertStringContainsString('<td>', $result);
    }

    public function test_purify_preserves_safe_list_elements()
    {
        $html = '<ul><li>Item 1</li></ul><ol><li>Item 2</li></ol>';
        $result = Purify::clean($html);

        $this->assertStringContainsString('<ul>', $result);
        $this->assertStringContainsString('<ol>', $result);
        $this->assertStringContainsString('<li>', $result);
    }

    public function test_purify_preserves_safe_link_with_https()
    {
        $result = Purify::clean('<a href="https://example.com" target="_blank">Link</a>');

        $this->assertStringContainsString('href', $result);
        $this->assertStringContainsString('https://example.com', $result);
        $this->assertStringContainsString('Link', $result);
    }

    public function test_purify_strips_javascript_hrefs()
    {
        $result = Purify::clean('<a href="javascript:alert(1)">Click</a>');

        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function test_purify_preserves_safe_img_with_https()
    {
        $result = Purify::clean('<img src="https://example.com/image.jpg" alt="Photo" style="width:100px">');

        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('https://example.com/image.jpg', $result);
    }

    public function test_purify_strips_iframe()
    {
        $result = Purify::clean('<iframe src="https://evil.com"></iframe>');

        $this->assertStringNotContainsString('<iframe', $result);
    }

    public function test_purify_strips_object_and_embed()
    {
        $result = Purify::clean('<object data="evil.swf"></object><embed src="evil.swf">');

        $this->assertStringNotContainsString('<object', $result);
        $this->assertStringNotContainsString('<embed', $result);
    }

    public function test_purify_strips_form_elements()
    {
        $result = Purify::clean('<form action="https://evil.com"><input type="text"><button>Submit</button></form>');

        $this->assertStringNotContainsString('<form', $result);
        $this->assertStringNotContainsString('<input', $result);
        $this->assertStringNotContainsString('<button', $result);
    }

    public function test_purify_handles_nested_xss_attempts()
    {
        $payloads = [
            '<div><script>alert(1)</script></div>',
            '<p><img src=x onerror=alert(1)></p>',
            '<table><tr><td><script>alert(1)</script></td></tr></table>',
            '<b><a href="javascript:alert(1)">click</a></b>',
        ];

        foreach ($payloads as $payload) {
            $result = Purify::clean($payload);
            $this->assertStringNotContainsString('<script>', $result, "Script tag survived in: {$payload}");
            $this->assertStringNotContainsString('javascript:', $result, "javascript: URI survived in: {$payload}");
            $this->assertDoesNotMatchRegularExpression('/\bon\w+\s*=/i', $result, "Event handler survived in: {$payload}");
        }
    }

    public function test_purify_strips_css_expression()
    {
        $result = Purify::clean('<div style="width: expression(alert(1))">test</div>');

        $this->assertStringNotContainsString('expression', $result);
        $this->assertStringNotContainsString('alert(', $result);
    }

    public function test_purify_strips_css_javascript_url()
    {
        $result = Purify::clean('<div style="background: url(javascript:alert(1))">test</div>');

        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function test_purify_short_string_skips_sanitization()
    {
        // Purify::clean() returns early for strings with length <= 1
        $result = Purify::clean('x');
        $this->assertEquals('x', $result);

        $result = Purify::clean('');
        $this->assertEquals('', $result);
    }

    public function test_purify_preserves_safe_inline_styles()
    {
        $result = Purify::clean('<div style="color: red; font-size: 14px; margin: 10px">Styled</div>');

        $this->assertStringContainsString('color:', $result);
        $this->assertStringContainsString('Styled', $result);
    }

    public function test_purify_preserves_data_attributes()
    {
        $result = Purify::clean('<div data-ref="invoice-table" data-element="product">Content</div>');

        $this->assertStringContainsString('data-ref', $result);
        $this->assertStringContainsString('data-element', $result);
    }

    // =========================================================================
    // Plain text with angle brackets — must not be wrapped in HTML tags
    // =========================================================================

    public function test_purify_returns_plain_text_with_angle_bracket_unchanged()
    {
        $text = '< i am a hairy ghost';
        $result = Purify::clean($text);

        $this->assertEquals($text, $result);
        $this->assertStringNotContainsString('<p>', $result);
        $this->assertStringNotContainsString('<html>', $result);
    }

    public function test_purify_returns_plain_text_with_math_symbols_unchanged()
    {
        $text = 'if x < 10 and y > 5 then proceed';
        $result = Purify::clean($text);

        $this->assertEquals($text, $result);
    }

    public function test_purify_returns_plain_text_with_multiple_angle_brackets_unchanged()
    {
        $text = 'price < $100 or quantity >= 50';
        $result = Purify::clean($text);

        $this->assertEquals($text, $result);
    }

    public function test_purify_strips_null_byte_xss_bypass()
    {
        $result = Purify::clean("<\x00script>alert(1)</\x00script>");

        $this->assertStringNotContainsString('alert(', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function test_purify_strips_null_bytes_from_event_handlers()
    {
        $result = Purify::clean("<img src=x on\x00error=alert(1)>");

        $this->assertStringNotContainsString('alert(', $result);
    }
}
