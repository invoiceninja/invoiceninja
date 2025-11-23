<?php

namespace App\Services\EDocument\Standards\Verifactu\Models;

class SistemaInformatico extends BaseXmlModel
{
    protected string $nombreRazon;
    protected ?string $nif = null;
    protected ?string $idOtro = null;
    protected string $nombreSistemaInformatico;
    protected string $idSistemaInformatico;
    protected string $version;
    protected string $numeroInstalacion;
    protected string $tipoUsoPosibleSoloVerifactu = 'S';
    protected string $tipoUsoPosibleMultiOT = 'S';
    protected string $indicadorMultiplesOT = 'S';

    public function __construct()
    {
        // Initialize required properties with default values
        $this->nombreRazon = 'InvoiceNinja System';
        $this->nombreSistemaInformatico = 'InvoiceNinja';
        $this->idSistemaInformatico = '01';
        $this->version = '1.0.0';
        $this->numeroInstalacion = '001';
        $this->nif = 'B12345678'; // Default NIF
    }

    public function toXml(\DOMDocument $doc): \DOMElement
    {
        $root = $this->createElement($doc, 'SistemaInformatico');

        // Add nombreRazon (first element in nested sequence)
        $root->appendChild($this->createElement($doc, 'NombreRazon', $this->nombreRazon));

        // Add either NIF or IDOtro (second element in nested sequence)
        if ($this->nif !== null) {
            $root->appendChild($this->createElement($doc, 'NIF', $this->nif));
        } elseif ($this->idOtro !== null) {
            $root->appendChild($this->createElement($doc, 'IDOtro', $this->idOtro));
        } else {
            // If neither NIF nor IDOtro is set, we need to set a default NIF
            $root->appendChild($this->createElement($doc, 'NIF', 'B12345678'));
        }

        // Add remaining elements (outside the nested sequence)
        $root->appendChild($this->createElement($doc, 'NombreSistemaInformatico', $this->nombreSistemaInformatico));
        $root->appendChild($this->createElement($doc, 'IdSistemaInformatico', $this->idSistemaInformatico));
        $root->appendChild($this->createElement($doc, 'Version', $this->version));
        $root->appendChild($this->createElement($doc, 'NumeroInstalacion', $this->numeroInstalacion));
        $root->appendChild($this->createElement($doc, 'TipoUsoPosibleSoloVerifactu', $this->tipoUsoPosibleSoloVerifactu));
        $root->appendChild($this->createElement($doc, 'TipoUsoPosibleMultiOT', $this->tipoUsoPosibleMultiOT));
        $root->appendChild($this->createElement($doc, 'IndicadorMultiplesOT', $this->indicadorMultiplesOT));

        return $root;
    }

    /**
     * Create a SistemaInformatico instance from XML string
     */
    public static function fromXml($xml): BaseXmlModel
    {
        if (is_string($xml)) {
            $doc = new \DOMDocument();
            $doc->loadXML($xml);
            $element = $doc->documentElement;
        } else {
            $element = $xml;
        }

        return self::fromDOMElement($element);
    }

    public static function fromDOMElement(\DOMElement $element): self
    {
        $sistemaInformatico = new self();

        // Parse NombreRazon
        $nombreRazonElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'NombreRazon')->item(0);
        if ($nombreRazonElement) {
            $sistemaInformatico->setNombreRazon($nombreRazonElement->nodeValue);
        }

        // Parse NIF or IDOtro
        $nifElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'NIF')->item(0);
        if ($nifElement) {
            $sistemaInformatico->setNif($nifElement->nodeValue);
        } else {
            $idOtroElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'IDOtro')->item(0);
            if ($idOtroElement) {
                $sistemaInformatico->setIdOtro($idOtroElement->nodeValue);
            }
        }

        // Parse remaining elements
        $nombreSistemaElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'NombreSistemaInformatico')->item(0);
        if ($nombreSistemaElement) {
            $sistemaInformatico->setNombreSistemaInformatico($nombreSistemaElement->nodeValue);
        }

        $idSistemaElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'IdSistemaInformatico')->item(0);
        if ($idSistemaElement) {
            $sistemaInformatico->setIdSistemaInformatico($idSistemaElement->nodeValue);
        }

        $versionElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'Version')->item(0);
        if ($versionElement) {
            $sistemaInformatico->setVersion($versionElement->nodeValue);
        }

        $numeroInstalacionElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'NumeroInstalacion')->item(0);
        if ($numeroInstalacionElement) {
            $sistemaInformatico->setNumeroInstalacion($numeroInstalacionElement->nodeValue);
        }

        $tipoUsoPosibleSoloVerifactuElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'TipoUsoPosibleSoloVerifactu')->item(0);
        if ($tipoUsoPosibleSoloVerifactuElement) {
            $sistemaInformatico->setTipoUsoPosibleSoloVerifactu($tipoUsoPosibleSoloVerifactuElement->nodeValue);
        }

        $tipoUsoPosibleMultiOTElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'TipoUsoPosibleMultiOT')->item(0);
        if ($tipoUsoPosibleMultiOTElement) {
            $sistemaInformatico->setTipoUsoPosibleMultiOT($tipoUsoPosibleMultiOTElement->nodeValue);
        }

        $indicadorMultiplesOTElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'IndicadorMultiplesOT')->item(0);
        if ($indicadorMultiplesOTElement) {
            $sistemaInformatico->setIndicadorMultiplesOT($indicadorMultiplesOTElement->nodeValue);
        }

        return $sistemaInformatico;
    }

    public function getNombreRazon(): string
    {
        return $this->nombreRazon;
    }

    public function setNombreRazon(string $nombreRazon): self
    {
        $this->nombreRazon = $nombreRazon;
        return $this;
    }

    public function getNif(): ?string
    {
        return $this->nif;
    }

    public function setNif(?string $nif): self
    {
        $this->nif = $nif;
        return $this;
    }

    public function getIdOtro(): ?string
    {
        return $this->idOtro;
    }

    public function setIdOtro(?string $idOtro): self
    {
        $this->idOtro = $idOtro;
        return $this;
    }

    public function getNombreSistemaInformatico(): string
    {
        return $this->nombreSistemaInformatico;
    }

    public function setNombreSistemaInformatico(string $nombreSistemaInformatico): self
    {
        $this->nombreSistemaInformatico = $nombreSistemaInformatico;
        return $this;
    }

    public function getIdSistemaInformatico(): string
    {
        return $this->idSistemaInformatico;
    }

    public function setIdSistemaInformatico(string $idSistemaInformatico): self
    {
        $this->idSistemaInformatico = $idSistemaInformatico;
        return $this;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;
        return $this;
    }

    public function getNumeroInstalacion(): string
    {
        return $this->numeroInstalacion;
    }

    public function setNumeroInstalacion(string $numeroInstalacion): self
    {
        $this->numeroInstalacion = $numeroInstalacion;
        return $this;
    }

    public function getTipoUsoPosibleSoloVerifactu(): string
    {
        return $this->tipoUsoPosibleSoloVerifactu;
    }

    public function setTipoUsoPosibleSoloVerifactu(string $tipoUsoPosibleSoloVerifactu): self
    {
        $this->tipoUsoPosibleSoloVerifactu = $tipoUsoPosibleSoloVerifactu;
        return $this;
    }

    public function getTipoUsoPosibleMultiOT(): string
    {
        return $this->tipoUsoPosibleMultiOT;
    }

    public function setTipoUsoPosibleMultiOT(string $tipoUsoPosibleMultiOT): self
    {
        $this->tipoUsoPosibleMultiOT = $tipoUsoPosibleMultiOT;
        return $this;
    }

    public function getIndicadorMultiplesOT(): string
    {
        return $this->indicadorMultiplesOT;
    }

    public function setIndicadorMultiplesOT(string $indicadorMultiplesOT): self
    {
        $this->indicadorMultiplesOT = $indicadorMultiplesOT;
        return $this;
    }
}
