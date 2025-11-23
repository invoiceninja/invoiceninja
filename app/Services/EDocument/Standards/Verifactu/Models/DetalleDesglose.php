<?php

namespace App\Services\EDocument\Standards\Verifactu\Models;

class DetalleDesglose extends BaseXmlModel
{
    protected array $desgloseIVA = [];

    public function setDesgloseIVA(array $desglose): self
    {
        $this->desgloseIVA = $desglose;
        return $this;
    }

    public function getDesgloseIVA(): array
    {
        return $this->desgloseIVA;
    }

    public function toXml(\DOMDocument $doc): \DOMElement
    {
        $root = $this->createElement($doc, 'DetalleDesglose');

        // Add IVA details directly under DetalleDesglose
        $root->appendChild($this->createElement($doc, 'Impuesto', $this->desgloseIVA['Impuesto']));

        if (isset($this->desgloseIVA['ClaveRegimen']) && in_array($this->desgloseIVA['ClaveRegimen'], ['01','03'])) {
            $root->appendChild($this->createElement($doc, 'ClaveRegimen', $this->desgloseIVA['ClaveRegimen']));
        }

        $root->appendChild($this->createElement($doc, 'CalificacionOperacion', $this->desgloseIVA['CalificacionOperacion']));

        if (isset($this->desgloseIVA['TipoImpositivo']) && $this->desgloseIVA['CalificacionOperacion'] == 'S1') {
            $root->appendChild($this->createElement($doc, 'TipoImpositivo', (string)$this->desgloseIVA['TipoImpositivo']));
        }
        $root->appendChild($this->createElement($doc, 'BaseImponibleOimporteNoSujeto', (string)$this->desgloseIVA['BaseImponible']));

        if (isset($this->desgloseIVA['Cuota']) && $this->desgloseIVA['CalificacionOperacion'] == 'S1') {
            $root->appendChild($this->createElement($doc, 'CuotaRepercutida', (string)$this->desgloseIVA['Cuota']));
        }

        return $root;
    }

    public static function fromDOMElement(\DOMElement $element): self
    {
        $detalleDesglose = new self();

        $desglose = [
            'Impuesto' => self::getElementText($element, 'Impuesto'),
            'ClaveRegimen' => self::getElementText($element, 'ClaveRegimen'),
            'CalificacionOperacion' => self::getElementText($element, 'CalificacionOperacion'),
            'BaseImponible' => (float)self::getElementText($element, 'BaseImponibleOimporteNoSujeto'),
            'TipoImpositivo' => (float)self::getElementText($element, 'TipoImpositivo'),
            'Cuota' => (float)self::getElementText($element, 'CuotaRepercutida')
        ];
        $detalleDesglose->setDesgloseIVA($desglose);

        return $detalleDesglose;
    }

    protected static function getElementText(\DOMElement $element, string $tagName): ?string
    {
        $node = $element->getElementsByTagNameNS(self::XML_NAMESPACE, $tagName)->item(0);
        return $node ? $node->nodeValue : null;
    }
}
