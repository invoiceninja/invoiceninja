<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
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
