<?php

namespace App\Services\EDocument\Standards\Verifactu\Models;

/**
 * DesgloseRectificacion - Rectification Breakdown
 *
 * This class represents the DesgloseRectificacionType from the Spanish tax authority schema.
 * It contains the breakdown of base and tax amounts for rectified invoices.
 */
class DesgloseRectificacion extends BaseXmlModel
{
    protected float $baseRectificada;
    protected float $cuotaRectificada;
    protected ?float $cuotaRecargoRectificado = null;

    public function __construct(float $baseRectificada, float $cuotaRectificada, ?float $cuotaRecargoRectificado = null)
    {
        $this->baseRectificada = $baseRectificada;
        $this->cuotaRectificada = $cuotaRectificada;
        $this->cuotaRecargoRectificado = $cuotaRecargoRectificado;
    }

    public function getBaseRectificada(): float
    {
        return $this->baseRectificada;
    }

    public function setBaseRectificada(float $baseRectificada): self
    {
        $this->baseRectificada = $baseRectificada;
        return $this;
    }

    public function getCuotaRectificada(): float
    {
        return $this->cuotaRectificada;
    }

    public function setCuotaRectificada(float $cuotaRectificada): self
    {
        $this->cuotaRectificada = $cuotaRectificada;
        return $this;
    }

    public function getCuotaRecargoRectificado(): ?float
    {
        return $this->cuotaRecargoRectificado;
    }

    public function setCuotaRecargoRectificado(?float $cuotaRecargoRectificado): self
    {
        $this->cuotaRecargoRectificado = $cuotaRecargoRectificado;
        return $this;
    }

    public function toXml(\DOMDocument $doc): \DOMElement
    {
        $root = $doc->createElementNS(self::XML_NAMESPACE, self::XML_NAMESPACE_PREFIX . ':ImporteRectificacion');

        // Add BaseRectificada (required)
        $root->appendChild($this->createElement($doc, 'BaseRectificada', number_format($this->baseRectificada, 2, '.', '')));

        // Add CuotaRectificada (required)
        $root->appendChild($this->createElement($doc, 'CuotaRectificada', number_format($this->cuotaRectificada, 2, '.', '')));

        // Add CuotaRecargoRectificado (optional)
        if ($this->cuotaRecargoRectificado !== null) {
            $root->appendChild($this->createElement($doc, 'CuotaRecargoRectificado', number_format($this->cuotaRecargoRectificado, 2, '.', '')));
        }

        return $root;
    }

    public static function fromDOMElement(\DOMElement $element): self
    {
        $baseRectificada = (float)self::getElementText($element, 'BaseRectificada');
        $cuotaRectificada = (float)self::getElementText($element, 'CuotaRectificada');
        $cuotaRecargoRectificado = self::getElementText($element, 'CuotaRecargoRectificado');

        return new self(
            $baseRectificada,
            $cuotaRectificada,
            $cuotaRecargoRectificado ? (float)$cuotaRecargoRectificado : null
        );
    }

    protected static function getElementText(\DOMElement $element, string $tagName): ?string
    {
        $node = $element->getElementsByTagNameNS(self::XML_NAMESPACE, $tagName)->item(0);
        return $node ? $node->nodeValue : null;
    }
}
