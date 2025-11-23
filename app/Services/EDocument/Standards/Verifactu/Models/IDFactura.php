<?php

namespace App\Services\EDocument\Standards\Verifactu\Models;

use DOMDocument;
use DOMElement;

class IDFactura extends BaseXmlModel
{
    protected string $idEmisorFactura;
    protected string $numSerieFactura;
    protected string $fechaExpedicionFactura;

    public function __construct()
    {
        // Initialize with default values
        $this->idEmisorFactura = 'B12345678';
        $this->numSerieFactura = '';
        $this->fechaExpedicionFactura = now()->format('d-m-Y');
    }

    public function getIdEmisorFactura(): string
    {
        return $this->idEmisorFactura;
    }

    public function setIdEmisorFactura(string $idEmisorFactura): self
    {
        $this->idEmisorFactura = $idEmisorFactura;
        return $this;
    }

    public function getNumSerieFactura(): string
    {
        return $this->numSerieFactura;
    }

    public function setNumSerieFactura(string $numSerieFactura): self
    {
        $this->numSerieFactura = $numSerieFactura;
        return $this;
    }

    public function getFechaExpedicionFactura(): string
    {
        return $this->fechaExpedicionFactura;
    }

    public function setFechaExpedicionFactura(string $fechaExpedicionFactura): self
    {
        $this->fechaExpedicionFactura = $fechaExpedicionFactura;
        return $this;
    }

    public function toXml(DOMDocument $doc): DOMElement
    {
        $idFactura = $this->createElement($doc, 'IDFactura');

        $idFactura->appendChild($this->createElement($doc, 'IDEmisorFactura', $this->idEmisorFactura));
        $idFactura->appendChild($this->createElement($doc, 'NumSerieFactura', $this->numSerieFactura));
        $idFactura->appendChild($this->createElement($doc, 'FechaExpedicionFactura', $this->fechaExpedicionFactura));

        return $idFactura;
    }

    public static function fromDOMElement(DOMElement $element): self
    {
        $idFactura = new self();

        // Parse IDEmisorFactura
        $idEmisorFactura = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'IDEmisorFactura')->item(0);
        if ($idEmisorFactura) {
            $idFactura->setIdEmisorFactura($idEmisorFactura->nodeValue);
        }

        // Parse NumSerieFactura
        $numSerieFactura = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'NumSerieFactura')->item(0);
        if ($numSerieFactura) {
            $idFactura->setNumSerieFactura($numSerieFactura->nodeValue);
        }

        // Parse FechaExpedicionFactura
        $fechaExpedicionFactura = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'FechaExpedicionFactura')->item(0);
        if ($fechaExpedicionFactura) {
            $idFactura->setFechaExpedicionFactura($fechaExpedicionFactura->nodeValue);
        }

        return $idFactura;
    }

    public function serialize(): string
    {
        return serialize($this);
    }

    public static function unserialize(string $data): self
    {
        $object = unserialize($data);

        if (!$object instanceof self) {
            throw new \InvalidArgumentException('Invalid serialized data - not an IDFactura object');
        }

        return $object;
    }
}
