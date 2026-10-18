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

namespace App\Services\ClientPortal;

use App\Services\Pdf\Purify;
use App\Utils\Ninja;

final class PortalHtmlSanitizer
{
    public function clean(string $html): string
    {
        if (Ninja::isHosted() !== true) {
            return $html;
        }

        return $this->stripStyleAttributes(Purify::cleanUntrustedDocument($html, true));
    }

    public function cleanSettings(array $settings): array
    {
        foreach (['portal_custom_head', 'portal_custom_footer'] as $field) {
            if (isset($settings[$field]) && is_string($settings[$field])) {
                $settings[$field] = $this->clean($settings[$field]);
            }
        }

        return $settings;
    }

    public function stripStyleAttributes(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previousErrorMode = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>'
                . '<body><div>' . $html . '</div></body></html>',
                LIBXML_NONET
            );

            if (!$loaded) {
                throw new \RuntimeException('Unable to parse portal HTML.');
            }

            foreach ($document->getElementsByTagName('style') as $style) {
                while ($style->attributes->length > 0) {
                    $style->removeAttributeNode($style->attributes->item(0));
                }
            }

            $body = $document->getElementsByTagName('body')->item(0);
            $wrapper = $body?->firstChild;

            if (!$wrapper instanceof \DOMElement || $wrapper->tagName !== 'div') {
                throw new \RuntimeException('Invalid portal HTML fragment.');
            }

            $result = '';

            // Keep siblings created when malformed markup closes the wrapper early.
            foreach ($body->childNodes as $child) {
                $nodes = $child === $wrapper ? $wrapper->childNodes : [$child];

                foreach ($nodes as $node) {
                    $serialized = $document->saveHTML($node);

                    if ($serialized === false) {
                        throw new \RuntimeException('Unable to serialize portal HTML.');
                    }

                    $result .= $serialized;
                }
            }

            return $result;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }
}
