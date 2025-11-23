<?php

namespace App\Services\EDocument\Standards\Verifactu\Models;

use App\Services\EDocument\Standards\Verifactu\Models\RegistroAnterior;

class Encadenamiento extends BaseXmlModel
{
    protected ?string $primerRegistro = null;
    protected ?RegistroAnterior $registroAnterior = null;
    protected ?RegistroAnterior $registroPosterior = null;

    public function toXml(\DOMDocument $doc): \DOMElement
    {
        $root = $this->createElement($doc, 'Encadenamiento');

        if ($this->registroAnterior !== null) {
            $root->appendChild($this->registroAnterior->toXml($doc));
        } else {
            // Always include PrimerRegistro if no RegistroAnterior is set
            $root->appendChild($this->createElement($doc, 'PrimerRegistro', 'S'));
        }

        if ($this->registroPosterior !== null) {
            $root->appendChild($this->registroPosterior->toXml($doc));
        }

        return $root;
    }

    public static function fromXml($xml): BaseXmlModel
    {
        $encadenamiento = new self();

        if (is_string($xml)) {
            error_log("Loading XML in Encadenamiento::fromXml: " . $xml);
            $dom = new \DOMDocument();
            if (!$dom->loadXML($xml)) {
                error_log("Failed to load XML in Encadenamiento::fromXml");
                throw new \DOMException('Invalid XML');
            }
            $element = $dom->documentElement;
        } else {
            $element = $xml;
        }

        try {
            // Handle PrimerRegistro
            $primerRegistro = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'PrimerRegistro')->item(0);
            if ($primerRegistro) {
                $encadenamiento->setPrimerRegistro($primerRegistro->nodeValue);
            }

            // Handle RegistroAnterior
            $registroAnterior = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'RegistroAnterior')->item(0);
            if ($registroAnterior) {
                $encadenamiento->setRegistroAnterior(RegistroAnterior::fromDOMElement($registroAnterior));
            }

            return $encadenamiento;
        } catch (\Exception $e) {
            error_log("Error parsing XML in Encadenamiento::fromXml: " . $e->getMessage());
            throw new \InvalidArgumentException('Error parsing XML: ' . $e->getMessage());
        }
    }

    public static function fromDOMElement(\DOMElement $element): self
    {
        $encadenamiento = new self();

        // Handle PrimerRegistro
        $primerRegistro = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'PrimerRegistro')->item(0);
        if ($primerRegistro) {
            $encadenamiento->setPrimerRegistro($primerRegistro->nodeValue);
        }

        // Handle RegistroAnterior
        $registroAnterior = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'RegistroAnterior')->item(0);
        if ($registroAnterior) {
            $encadenamiento->setRegistroAnterior(RegistroAnterior::fromDOMElement($registroAnterior));
        }

        return $encadenamiento;
    }

    public function getPrimerRegistro(): ?string
    {
        return $this->primerRegistro;
    }

    public function setPrimerRegistro(?string $primerRegistro): self
    {
        if ($primerRegistro !== null && $primerRegistro !== 'S') {
            throw new \InvalidArgumentException('PrimerRegistro must be "S" or null');
        }
        $this->primerRegistro = $primerRegistro;
        return $this;
    }

    public function getRegistroAnterior(): ?RegistroAnterior
    {
        return $this->registroAnterior;
    }

    public function setRegistroAnterior(?RegistroAnterior $registroAnterior): self
    {
        $this->registroAnterior = $registroAnterior;
        return $this;
    }

    public function getRegistroPosterior(): ?RegistroAnterior
    {
        return $this->registroPosterior;
    }

    public function setRegistroPosterior(?RegistroAnterior $registroPosterior): self
    {
        $this->registroPosterior = $registroPosterior;
        return $this;
    }
}
