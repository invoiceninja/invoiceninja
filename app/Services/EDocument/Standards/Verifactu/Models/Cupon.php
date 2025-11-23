<?php

namespace App\Services\EDocument\Standards\Verifactu\Models;

class Cupon extends BaseXmlModel
{
    protected string $idCupon;
    protected string $fechaExpedicionCupon;
    protected float $importeCupon;
    protected ?string $descripcionCupon = null;

    public function toXml(\DOMDocument $doc): \DOMElement
    {
        $root = $this->createElement($doc, 'Cupon');

        // Add required elements
        $root->appendChild($this->createElement($doc, 'IDCupon', $this->idCupon));
        $root->appendChild($this->createElement($doc, 'FechaExpedicionCupon', $this->fechaExpedicionCupon));
        $root->appendChild($this->createElement($doc, 'ImporteCupon', (string)$this->importeCupon));

        // Add optional description
        if ($this->descripcionCupon !== null) {
            $root->appendChild($this->createElement($doc, 'DescripcionCupon', $this->descripcionCupon));
        }

        return $root;
    }

    public static function fromDOMElement(\DOMElement $element): self
    {
        $cupon = new self();
        $cupon->setIdCupon($cupon->getElementValue($element, 'IDCupon'));
        $cupon->setFechaExpedicionCupon($cupon->getElementValue($element, 'FechaExpedicionCupon'));
        $cupon->setImporteCupon((float)$cupon->getElementValue($element, 'ImporteCupon'));

        $descripcionCupon = $cupon->getElementValue($element, 'DescripcionCupon');
        if ($descripcionCupon !== null) {
            $cupon->setDescripcionCupon($descripcionCupon);
        }

        return $cupon;
    }

    public function getIdCupon(): string
    {
        return $this->idCupon;
    }

    public function setIdCupon(string $idCupon): self
    {
        $this->idCupon = $idCupon;
        return $this;
    }

    public function getFechaExpedicionCupon(): string
    {
        return $this->fechaExpedicionCupon;
    }

    public function setFechaExpedicionCupon(string $fechaExpedicionCupon): self
    {
        $this->fechaExpedicionCupon = $fechaExpedicionCupon;
        return $this;
    }

    public function getImporteCupon(): float
    {
        return $this->importeCupon;
    }

    public function setImporteCupon(float $importeCupon): self
    {
        $this->importeCupon = $importeCupon;
        return $this;
    }

    public function getDescripcionCupon(): ?string
    {
        return $this->descripcionCupon;
    }

    public function setDescripcionCupon(?string $descripcionCupon): self
    {
        $this->descripcionCupon = $descripcionCupon;
        return $this;
    }
}
