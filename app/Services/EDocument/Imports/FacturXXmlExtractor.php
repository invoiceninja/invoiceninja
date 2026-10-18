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

namespace App\Services\EDocument\Imports;

use DOMDocument;
use DOMXPath;
use UnexpectedValueException;
use horstoeko\zugferd\ZugferdDocumentPdfReader;
use horstoeko\zugferd\ZugferdDocumentReader;

final class FacturXXmlExtractor
{
    private const CII_NAMESPACE = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';

    private const RAM_NAMESPACE = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';

    private const BASIC_PROFILE = 'urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:basic';

    private const BASIC_PROFILE_WITH_COLON_SEPARATORS = 'urn:cen.eu:en16931:2017:compliant:factur-x.eu:1p0:basic';

    private const EXTENDED_PROFILE = 'urn:cen.eu:en16931:2017#conformant#urn:factur-x.eu:1p0:extended';

    /**
     * Extracts the CII invoice XML from a Factur-X PDF or accepts a standalone
     * Factur-X/ZUGFeRD CII XML document.
     */
    public function extract(string $documentContent): string
    {
        if ($documentContent === '') {
            throw new UnexpectedValueException('The Factur-X document is empty.');
        }

        $xml = $this->isPdf($documentContent)
            ? ZugferdDocumentPdfReader::getXmlFromContent($documentContent)
            : $documentContent;

        $this->assertCrossIndustryInvoice($xml);

        return $xml;
    }

    /**
     * Creates a reader while retaining the original extracted XML unchanged.
     * Compatible guideline identifiers unsupported by the reader are mapped
     * only in its internal copy.
     */
    public function read(string $documentContent): ZugferdDocumentReader
    {
        $xml = $this->extract($documentContent);

        return ZugferdDocumentReader::readAndGuessFromContent(
            $this->normalizeProfileForReader($xml)
        );
    }

    public function isPdf(string $documentContent): bool
    {
        return str_contains(substr($documentContent, 0, 1024), '%PDF-');
    }

    private function assertCrossIndustryInvoice(string $xml): void
    {
        $previousInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = new DOMDocument();

            if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)
                || ! $document->documentElement
                || $document->documentElement->localName !== 'CrossIndustryInvoice'
                || $document->documentElement->namespaceURI !== self::CII_NAMESPACE) {
                throw new UnexpectedValueException('The document does not contain a Factur-X CII invoice payload.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousInternalErrors);
        }
    }

    private function normalizeProfileForReader(string $xml): string
    {
        $document = new DOMDocument();
        $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('rsm', self::CII_NAMESPACE);
        $xpath->registerNamespace('ram', self::RAM_NAMESPACE);
        $guidelineIds = $xpath->query('/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID');

        if ($guidelineIds === false || $guidelineIds->length === 0) {
            return $xml;
        }

        $guidelineId = $guidelineIds->item(0);

        if (! $guidelineId) {
            return $xml;
        }

        $normalizedProfile = match (true) {
            trim($guidelineId->textContent) === self::BASIC_PROFILE_WITH_COLON_SEPARATORS => self::BASIC_PROFILE,
            str_contains(strtolower($guidelineId->textContent), 'extended-ctc-fr') => self::EXTENDED_PROFILE,
            default => null,
        };

        if ($normalizedProfile === null) {
            return $xml;
        }

        $guidelineId->nodeValue = $normalizedProfile;
        $normalizedXml = $document->saveXML();

        if ($normalizedXml === false) {
            throw new UnexpectedValueException('The Factur-X CII invoice payload could not be prepared for parsing.');
        }

        return $normalizedXml;
    }
}
