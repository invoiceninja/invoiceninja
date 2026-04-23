<?php

namespace App\Services\Pdf;

class Purify
{
    private static array $allowed_elements = [
        // Document structure
        'html', 'head', 'body', 'meta', 'title', 'style',

        // Root element
        'root',

        // Block Elements
        'div', 'p', 'section', 'header', 'footer',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'blockquote', 'pre',

        // Text Elements
        'span', 'strong', 'em', 'b', 'i', 'u', 'small',
        'sub', 'sup', 'del', 'ins', 'code', 's', 'mark',
        'abbr', 'q', 'cite',

        // Line Breaks
        'br', 'hr',

        // Lists
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',

        // Tables
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'caption', 'colgroup', 'col',

        // Media & Links
        'img', 'a',

        // Figures
        'figure', 'figcaption',

        // Address
        'address',

        // Template specific
        'ninja',

        // SVG Elements
        'svg', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline',
        'polygon', 'g', 'text', 'tspan', 'defs', 'use', 'title',
    ];


    private static array $allowed_attributes = [
        // Global Attributes
        'class' => ['*'],
        'id' => ['*'],
        'style' => ['*'],
        'title' => ['*'],
        'lang' => ['*'],
        'dir' => ['*'],  // Allow all dir values
        'tabindex' => ['*'],
        'data-*' => ['*'], // Custom data attributes
        'data-ref' => ['*'],
        'data-element' => ['*'],
        'data-state' => ['*'],

        //SVG
        'd' => ['*'],
        'viewBox' => ['*'],
        'viewbox' => ['*'], // DOMDocument lowercases viewBox
        'xmlns' => ['http://www.w3.org/2000/svg'],
        'fill' => ['*'],
        'stroke' => ['*'],
        'stroke-width' => ['*'],
        'cx' => ['*'],
        'cy' => ['*'],
        'r' => ['*'],
        'x' => ['*'],
        'y' => ['*'],
        'transform' => ['*'],
        'points' => ['*'],
        'preserveAspectRatio' => ['*'],
        'preserveaspectratio' => ['*'], // DOMDocument lowercases preserveAspectRatio
        'version' => ['*'],
        'xlink:href' => ['#*'], // Only allow internal references
        'fill-rule' => ['nonzero', 'evenodd'],
        // Layout & Presentation
        'align' => ['left', 'center', 'right', 'justify'],
        'valign' => ['top', 'middle', 'bottom', 'baseline'],
        'width' => ['*'],
        'height' => ['*'],
        'cellspacing' => ['*'],
        'cellpadding' => ['*'],
        'border' => ['*'],
        'min-width' => ['*'],
        'max-width' => ['*'],

        // Table-specific
        'colspan' => ['*'],
        'rowspan' => ['*'],
        'scope' => ['row', 'col', 'rowgroup', 'colgroup'],
        'headers' => ['*'],

        // Links & Media
        'href' => ['http://*', 'https://*', 'data:image/*', '${*}', '$*.*'],
        'src' => ['http://*', 'https://*', 'data:image/*', '${*}', '$*.*'],
        'alt' => ['*'],
        'target' => ['_blank', '_self'],
        'rel' => ['nofollow', 'noopener', 'noreferrer'],

        // Lists
        'type' => ['1', 'A', 'a', 'I', 'i', 'disc', 'circle', 'square'],
        'start' => ['*'],

        // Accessibility
        'aria-*' => ['*'],
        'role' => ['*'],

        // Template specific
        'hidden' => ['*'],
        'zoom' => ['*'],
        'size' => ['*'],

        // Meta tag attributes
        'charset' => ['*'],
        'name' => ['*'],
        'content' => ['*'],
        'http-equiv' => ['cache-control'],
        'viewport' => ['*'],
        'xmlns' => ['http://www.w3.org/2000/svg'],
    ];

    private static array $dangerous_css_patterns = [
        // JavaScript execution patterns
        '/expression\s*\(/', // CSS expressions
        '/javascript\s*:/',  // JavaScript protocol
        '/behaviour\s*:/',   // IE behavior
        '/-moz-binding\s*:/', // Mozilla binding

        // URL patterns that might lead to script execution
        '/url\s*\(\s*[^)]*(?:javascript|data|vbscript)/i',

        // Import directives
        '/@import\s/',           // Added proper delimiters

        // Other potentially dangerous properties
        '/-o-link\s*:/',
        '/-o-link-source\s*:/',
        '/-o-replace\s*:/',
        '/call\s*\(/',
        '/position\s*:\s*fixed/i',

        // Common attack vectors
        '/background(?:-image)?\s*:\s*[^;]*(?:url|expression|javascript|data|vbscript)/i',

        // IE-specific expressions
        '/progid\s*:/',
        '/setExpression\s*\(/',
        '/AlphaImageLoader\s*\(/',
        '/chrome-extension\s*:/',
        '/file\s*:/',
        '/ftp\s*:/',
        '/gopher\s*:/',
        '/ws\s*:/',
        '/wss\s*:/',
    ];

    private static array $dangerous_css_properties = [
        'behavior',
        '-moz-binding',
        'pointer-events',
        'expression',
        'clip-path',
        'mask',
        'filter',
        'backdrop-filter',
    ];

    /**
     * Filter CSS to remove potentially dangerous styles
     */
    private static function filterCssStyles(string $css): string
    {
        // Remove comments that might hide malicious code
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);

        // Convert to lowercase for consistent checking
        $css_lower = strtolower($css);

        // Check for dangerous patterns
        foreach (self::$dangerous_css_patterns as $pattern) {
            if (preg_match($pattern, $css_lower)) {
                return ''; // Return empty if dangerous pattern found
            }
        }

        // Split into individual declarations
        $declarations = array_filter(array_map('trim', explode(';', $css)));
        $safe_declarations = [];

        foreach ($declarations as $declaration) {
            // Split property and value
            $parts = array_map('trim', explode(':', $declaration, 2));
            if (count($parts) !== 2) {
                continue;
            }

            [$property, $value] = $parts;
            $property = strtolower($property);

            // Skip dangerous properties
            if (in_array($property, self::$dangerous_css_properties)) {
                continue;
            }

            // Additional URL safety check
            if (stripos($value, 'url(') !== false) {
                // Only allow specific URL patterns
                if (!preg_match('/url\s*\(\s*[\'"]?(https?:\/\/[^"\'\)]+)[\'"]?\s*\)/i', $value)) {
                    continue;
                }
            }

            $safe_declarations[] = $property . ': ' . $value;
        }

        return implode('; ', $safe_declarations);
    }

    /**
     * Sanitize CSS inside <style> blocks to remove rules targeting the whitelabel logo.
     */
    private static function sanitizeStyleBlockContent(string $css): string
    {

        $css = preg_replace('/\/\*.*?\*\//s', '', $css);

        $css = preg_replace('/[^{}]*invoiceninja[\-_]whitelabel[^{}]*\{[^}]*\}/i', '', $css);

        // Normalize CSS unicode escapes before filtering
        $css = preg_replace_callback(
            '/\\\\([0-9a-fA-F]{1,6})\s?/',
            fn($m) => mb_chr(intval($m[1], 16)),
            $css
        );

        // Block http:// (SSRF vector to internal services) and file:// (local file access)
        $css = preg_replace('/http\s*:\s*\/\//i', '', $css);
        $css = preg_replace('/file\s*:\s*\/\//i', '', $css);

        return $css;
    }

    private static array $dangerous_svg_elements = [
        'script',
        'handler',
        'foreignObject',
        'annotation-xml',
        'color-profile',
        'style',  // or carefully sanitize if needed
        'onload',
        'onerror',
        'onunload',
        'onabort',
    ];

    private static function isDangerousSvgElement(string $tagName): bool
    {
        return in_array(strtolower($tagName), self::$dangerous_svg_elements);
    }
    
    /**
     * clean
     *
     * @param  string $html
     * @param  bool $is_fragment
     * @return string
     */
    public static function clean(string $html, bool $is_fragment = false): string
    {
        
        if (config('ninja.disable_purify_html') || strlen($html) <= 1) {
            return str_replace('%24', '$', $html);
        }

        $html = str_replace('%24', '$', $html);

        // Strip null bytes — no legitimate use in text, and they can be used
        // to bypass HTML tag detection (e.g. "<\x00script>")
        $html = str_replace("\x00", '', $html);

        // If the string contains no actual HTML tags, return it unchanged.
        // This avoids DOMDocument wrapping plain text like "< i am text" in <p> tags.
        // Real HTML tags start with < followed by a letter, / or !
        if (!preg_match('/<[a-zA-Z\/!]/', $html)) {
            return $html;
        }

        libxml_use_internal_errors(true);

        $document = new \DOMDocument();

        // Wrap fragments in a <div> container so DOMDocument does not inject
        // <p> tags around loose text that precedes block-level elements.
        $html = $is_fragment
            ? '<?xml encoding="UTF-8"><div>' . $html . '</div>'
            : '<?xml encoding="UTF-8">' . $html;

        @$document->loadHTML(htmlspecialchars_decode(htmlspecialchars($html, ENT_QUOTES, 'UTF-8')), LIBXML_NONET);

        // Function to recursively check nodes
        $cleanNodes = function ($node) use (&$cleanNodes) {

            $allowed_elements = self::$allowed_elements;
            $allowed_attributes = self::$allowed_attributes;

            if (!$node) {
                return;
            }

            // Store children in array first to avoid modification during iteration
            $children = [];
            if ($node->hasChildNodes()) {
                foreach ($node->childNodes as $child) {
                    $children[] = $child;
                }
            }

            // Process each child
            foreach ($children as $child) {
                $cleanNodes($child);
            }

            // Only process element nodes
            if ($node instanceof \DOMElement) {
                // Remove element if not in allowed list
                if (!in_array(strtolower($node->tagName), $allowed_elements)) {
                    if ($node->parentNode) {
                        $node->parentNode->removeChild($node);
                    }
                    return;
                }

                // Sanitize <style> block content to protect whitelabel logo
                if (strtolower($node->tagName) === 'style') {
                    $node->textContent = self::sanitizeStyleBlockContent($node->textContent);
                    return;
                }

                // Store current attributes before removing them
                $current_attributes = [];
                foreach ($node->attributes as $attr) {
                    $current_attributes[$attr->name] = $attr->value;
                }

                // Remove ALL attributes from the node, then re-add only allowed ones below
                $attributes_to_remove = [];
                foreach ($node->attributes as $attr) {
                    $attributes_to_remove[] = $attr->nodeName;
                }
                foreach ($attributes_to_remove as $attr_name) {
                    $node->removeAttribute($attr_name);
                }

                // Then add back only the allowed attributes
                foreach ($current_attributes as $name => $value) {
                    $attr_name = strtolower($name);

                    // Add special handling for style attributes
                    if ($attr_name === 'style') {
                        $filtered_css = self::filterCssStyles($value);
                        if (!empty($filtered_css)) {
                            $node->setAttribute($name, $filtered_css);
                        }
                        continue;
                    }

                    // Handle data-* attributes
                    if (strpos($attr_name, 'data-') === 0 && isset($allowed_attributes['data-*'])) {
                        $node->setAttribute($name, $value);
                        continue;
                    }

                    // Handle aria-* attributes
                    if (strpos($attr_name, 'aria-') === 0 && isset($allowed_attributes['aria-*'])) {
                        $node->setAttribute($name, $value);
                        continue;
                    }

                    // Skip if attribute isn't in allowed list
                    if (!isset($allowed_attributes[$attr_name])) {
                        continue;
                    }

                    $allowed_values = $allowed_attributes[$attr_name];

                    // Special handling for URLs (src and href)
                    if (($attr_name === 'src' || $attr_name === 'href') && !empty($allowed_values)) {
                        $is_allowed = false;

                        // Debug log
                        // nlog("Checking URL attribute {$attr_name} with value: {$value}");

                        foreach ($allowed_values as $pattern) {
                            // Fix the pattern conversion for URL matching
                            if ($pattern === 'http://*') {
                                // nlog("http://* regex");
                                $regex = '^http\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}(\/\S*)?$';
                            } elseif ($pattern === 'https://*') {
                                // nlog("https://* regex");
                                $regex = '^https\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}(\/\S*)?$';
                            } elseif ($pattern === 'data:image/*') {
                                // nlog("data:image/* regex");
                                $regex = '^data\:image\/[a-zA-Z0-9\+]+;base64,.*$';
                            } else {
                                $regex = preg_quote($pattern, '/');
                                $regex = str_replace('\*', '.*', $regex);
                            }

                            if (preg_match('/' . $regex . '/i', $value)) {
                                $is_allowed = true;
                                break;
                            }
                        }

                        if ($is_allowed) {
                            $node->setAttribute($name, $value);
                        } else {
                        }
                        continue;
                    }

                    // For attributes that allow all values
                    if ($allowed_values === ['*']) {
                        $node->setAttribute($name, $value);
                        continue;
                    }

                    // For attributes with specific allowed values
                    if (in_array($value, $allowed_values)) {
                        $node->setAttribute($name, $value);
                    }
                }
            }
        };

        try {

            $cleanNodes($document->documentElement);

            if ($is_fragment) {
                // Extract content from inside the wrapper <div> we added before parsing.
                $body = $document->getElementsByTagName('body')->item(0);
                $html = '';
                if ($body) {
                    $wrapper = $body->firstChild;
                    if ($wrapper && $wrapper->nodeName === 'div') {
                        foreach ($wrapper->childNodes as $child) {
                            $html .= $document->saveHTML($child);
                        }
                    } else {
                        foreach ($body->childNodes as $child) {
                            $html .= $document->saveHTML($child);
                        }
                    }
                }
            } else {
                $html = $document->saveHTML();
            }

            $html = str_replace('%24', '$', $html);

            // nlog("post purify => {$html}");
            return $html;

        } catch (\Exception $e) {

            nlog('Error cleaning HTML: ' . $e->getMessage());

            libxml_clear_errors();

            throw new \RuntimeException('HTML sanitization failed');
        } finally {
            libxml_clear_errors();
        }

    }

}
