<?php

namespace App\Services\EDocument\Standards\Verifactu\Models;

/**
 * RegistroAnterior - Previous Record Information
 *
 * This class represents the previous record information required for Verifactu e-invoicing
 * chain linking. It contains the details of the previous invoice in the chain.
 */
class RegistroAnterior extends BaseXmlModel
{
    protected string $idEmisorFactura;
    protected string $numSerieFactura;
    protected string $fechaExpedicionFactura;
    protected string $huella;

    public function toXml(\DOMDocument $doc): \DOMElement
    {
        $root = $this->createElement($doc, 'RegistroAnterior');

        $root->appendChild($this->createElement($doc, 'IDEmisorFactura', $this->idEmisorFactura));
        $root->appendChild($this->createElement($doc, 'NumSerieFactura', $this->numSerieFactura));
        $root->appendChild($this->createElement($doc, 'FechaExpedicionFactura', $this->fechaExpedicionFactura));
        $root->appendChild($this->createElement($doc, 'Huella', $this->huella));

        return $root;
    }

    public static function fromDOMElement(\DOMElement $element): self
    {
        $registroAnterior = new self();

        // Handle IDEmisorFactura
        $idEmisorFactura = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'IDEmisorFactura')->item(0);
        if ($idEmisorFactura) {
            $registroAnterior->setIdEmisorFactura($idEmisorFactura->nodeValue);
        }

        // Handle NumSerieFactura
        $numSerieFactura = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'NumSerieFactura')->item(0);
        if ($numSerieFactura) {
            $registroAnterior->setNumSerieFactura($numSerieFactura->nodeValue);
        }

        // Handle FechaExpedicionFactura
        $fechaExpedicionFactura = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'FechaExpedicionFactura')->item(0);
        if ($fechaExpedicionFactura) {
            $registroAnterior->setFechaExpedicionFactura($fechaExpedicionFactura->nodeValue);
        }

        // Handle Huella
        $huella = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'Huella')->item(0);
        if ($huella) {
            $registroAnterior->setHuella($huella->nodeValue);
        }

        return $registroAnterior;
    }

    public static function fromXml($xml): self
    {
        if ($xml instanceof \DOMElement) {
            return static::fromDOMElement($xml);
        }

        if (!is_string($xml)) {
            throw new \InvalidArgumentException('Input must be either a string or DOMElement');
        }

        // Enable user error handling for XML parsing
        $previousErrorSetting = libxml_use_internal_errors(true);

        try {
            $doc = new \DOMDocument();
            if (!$doc->loadXML($xml)) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                throw new \DOMException('Failed to load XML: ' . ($errors ? $errors[0]->message : 'Invalid XML format'));
            }
            return static::fromDOMElement($doc->documentElement);
        } finally {
            // Restore previous error handling setting
            libxml_use_internal_errors($previousErrorSetting);
        }
    }

    /**
     * Get the NIF of the invoice issuer from the previous record
     */
    public function getIdEmisorFactura(): string
    {
        return $this->idEmisorFactura;
    }

    /**
     * Set the NIF of the invoice issuer from the previous record
     */
    public function setIdEmisorFactura(string $idEmisorFactura): self
    {
        $this->idEmisorFactura = $idEmisorFactura;
        return $this;
    }

    /**
     * Get the invoice number from the previous record
     */
    public function getNumSerieFactura(): string
    {
        return $this->numSerieFactura;
    }

    /**
     * Set the invoice number from the previous record
     */
    public function setNumSerieFactura(string $numSerieFactura): self
    {
        $this->numSerieFactura = $numSerieFactura;
        return $this;
    }

    /**
     * Get the invoice issue date from the previous record
     */
    public function getFechaExpedicionFactura(): string
    {
        return $this->fechaExpedicionFactura;
    }

    /**
     * Set the invoice issue date from the previous record
     *
     * @param string $fechaExpedicionFactura Date in DD-MM-YYYY format
     */
    public function setFechaExpedicionFactura(string $fechaExpedicionFactura): self
    {
        $this->fechaExpedicionFactura = $fechaExpedicionFactura;
        return $this;
    }

    /**
     * Get the digital fingerprint/hash from the previous record
     */
    public function getHuella(): string
    {
        return $this->huella;
    }

    /**
     * Set the digital fingerprint/hash from the previous record
     */
    public function setHuella(string $huella): self
    {
        $this->huella = $huella;
        return $this;
    }
}
