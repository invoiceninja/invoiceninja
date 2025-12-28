<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\EDocument\Standards\Verifactu\Models;

interface XmlModelInterface
{
    public function toXmlString(): string;

    public function toXml(\DOMDocument $doc): \DOMElement;

    public function toSoapEnvelope(): string;
}
