<?php

namespace App\Services\EDocument\Standards\Verifactu\Models;

abstract class BaseXmlModel
{
    public const XML_NAMESPACE = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';
    protected const XML_NAMESPACE_PREFIX = 'sum1';
    protected const XML_DS_NAMESPACE = 'http://www.w3.org/2000/09/xmldsig#';
    protected const XML_DS_NAMESPACE_PREFIX = 'ds';

    protected function createElement(\DOMDocument $doc, string $name, ?string $value = null, array $attributes = []): \DOMElement
    {
        $element = $doc->createElementNS(self::XML_NAMESPACE, self::XML_NAMESPACE_PREFIX . ':' . $name);
        if ($value !== null) {
            $textNode = $doc->createTextNode($value);
            $element->appendChild($textNode);
        }
        foreach ($attributes as $attrName => $attrValue) {
            $element->setAttribute($attrName, $attrValue);
        }
        return $element;
    }

    protected function createDsElement(\DOMDocument $doc, string $name, ?string $value = null, array $attributes = []): \DOMElement
    {
        $element = $doc->createElementNS(self::XML_DS_NAMESPACE, self::XML_DS_NAMESPACE_PREFIX . ':' . $name);
        if ($value !== null) {
            $textNode = $doc->createTextNode($value);
            $element->appendChild($textNode);
        }
        foreach ($attributes as $attrName => $attrValue) {
            $element->setAttribute($attrName, $attrValue);
        }
        return $element;
    }

    protected function getElementValue(\DOMElement $parent, string $name, string $namespace = self::XML_NAMESPACE): ?string
    {
        $elements = $parent->getElementsByTagNameNS($namespace, $name);
        if ($elements->length > 0) {
            return $elements->item(0)->textContent;
        }
        return null;
    }

    abstract public function toXml(\DOMDocument $doc): \DOMElement;

    public static function fromXml($xml): self
    {
        if ($xml instanceof \DOMElement) {
            return static::fromDOMElement($xml);
        }

        if (!is_string($xml)) {
            throw new \InvalidArgumentException('Input must be either a string or DOMElement');
        }

        $doc = new \DOMDocument();
        $doc->formatOutput = true;
        $doc->preserveWhiteSpace = false;
        if (!$doc->loadXML($xml)) {
            throw new \DOMException('Failed to load XML: Invalid XML format');
        }
        return static::fromDOMElement($doc->documentElement);
    }

    abstract public static function fromDOMElement(\DOMElement $element): self;
}
