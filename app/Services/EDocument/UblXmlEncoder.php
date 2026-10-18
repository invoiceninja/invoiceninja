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

namespace App\Services\EDocument;

/**
 * Wraps EInvoice-encoded Peppol payload bytes in a UBL 2.1 document root.
 *
 * EInvoice::encode() emits a prolog plus namespaced fragment (no document root).
 * This helper replaces the prolog with a UBL root element and appends the
 * matching closing tag. Payload text content is never modified.
 */
final class UblXmlEncoder
{
    public static function wrap(string $encodedXml, UblDocumentKind $kind): string
    {
        if ($kind === UblDocumentKind::CreditNote) {
            $prefix = '<?xml version="1.0" encoding="UTF-8"?>
<CreditNote xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
    xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
    xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2">';
            $suffix = '</CreditNote>';
        } else {
            $prefix = '<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
    xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
    xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">';
            $suffix = '</Invoice>';
        }

        $body = preg_replace('/^\s*<\?xml\s+version="1\.0"(\s+encoding="[^"]*")?\?\>\s*/i', '', $encodedXml, 1);

        return $prefix . ltrim((string) $body) . $suffix;
    }
}
