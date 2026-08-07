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

namespace App\Services\Quickbooks;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Throwable;

class QuickbooksFaultParser
{
    public function parse(Throwable|string $failure): QuickbooksFault
    {
        $raw_message = $failure instanceof Throwable ? $failure->getMessage() : $failure;
        $http_status = $this->extractHttpStatus($raw_message);

        if ($http_status === null && $failure instanceof Throwable) {
            $code = $failure->getCode();
            $http_status = is_int($code) && $code >= 100 && $code <= 599 ? $code : null;
        }
        $xml_body = $this->extractXmlBody($raw_message);

        if ($xml_body === null) {
            return $this->fallback($http_status, $raw_message);
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument();

            if (!$document->loadXML($xml_body, LIBXML_NONET | LIBXML_NOBLANKS)) {
                return $this->fallback($http_status, $raw_message);
            }

            $xpath = new DOMXPath($document);
            $fault = $xpath->query('//*[local-name()="Fault"]')->item(0);
            $fault_type = $fault instanceof DOMElement ? $fault->getAttribute('type') : '';
            $errors = [];

            foreach ($xpath->query('//*[local-name()="Fault"]/*[local-name()="Error"]') as $error) {
                if (!$error instanceof DOMElement) {
                    continue;
                }

                $errors[] = [
                    'code' => $error->getAttribute('code'),
                    'element' => $error->getAttribute('element'),
                    'message' => $this->childValue($xpath, $error, 'Message'),
                    'detail' => $this->childValue($xpath, $error, 'Detail'),
                ];
            }

            return new QuickbooksFault(
                http_status: $http_status,
                fault_type: $fault_type,
                errors: $errors,
                fallback_message: $this->cleanFallbackMessage($raw_message),
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function humanMessage(Throwable|string $failure, ?string $operation = null): string
    {
        return $this->parse($failure)->humanMessage($operation);
    }

    public function statusMessage(Throwable|string $failure, ?string $operation = null): string
    {
        return $this->parse($failure)->statusMessage($operation);
    }

    private function fallback(?int $http_status, string $message): QuickbooksFault
    {
        return new QuickbooksFault(
            http_status: $http_status,
            fault_type: '',
            errors: [],
            fallback_message: $this->cleanFallbackMessage($message),
        );
    }

    private function extractHttpStatus(string $message): ?int
    {
        if (preg_match('/Response Code:\\[(\\d{3})\\]/', $message, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function extractXmlBody(string $message): ?string
    {
        if (preg_match('/(<\\?xml\\b.*?<\\/IntuitResponse>)/s', $message, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/(<IntuitResponse\\b.*?<\\/IntuitResponse>)/s', $message, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function childValue(DOMXPath $xpath, DOMElement $error, string $name): string
    {
        $node = $xpath->query('./*[local-name()="' . $name . '"]', $error)->item(0);

        return $node ? trim(preg_replace('/\\s+/', ' ', $node->textContent) ?? '') : '';
    }

    private function cleanFallbackMessage(string $message): string
    {
        $cleaned = str_replace('Request is not made successful. ', '', $message);

        if (($position = strpos($cleaned, ' with body: [')) !== false) {
            $cleaned = substr($cleaned, 0, $position);
        }

        return mb_substr(trim($cleaned), 0, 900);
    }
}
