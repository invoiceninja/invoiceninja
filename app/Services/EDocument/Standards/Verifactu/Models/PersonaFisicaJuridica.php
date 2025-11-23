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

use App\Services\EDocument\Standards\Verifactu\Models\BaseXmlModel;

class PersonaFisicaJuridica extends BaseXmlModel
{
    protected ?string $nif = null;
    protected ?string $nombreRazon = null;
    protected ?string $apellidos = null;
    protected ?string $nombre = null;
    protected ?string $razonSocial = null;
    protected ?string $tipoIdentificacion = null;
    protected ?IDOtro $idOtro = null;
    protected ?string $pais = null;

    public function getNif(): ?string
    {
        return $this->nif;
    }

    public function setNif(?string $nif): self
    {
        if ($nif !== null && strlen($nif) !== 9) {
            throw new \InvalidArgumentException('NIF must be exactly 9 characters long');
        }
        $this->nif = $nif;
        return $this;
    }

    public function getNombreRazon(): ?string
    {
        return $this->nombreRazon;
    }

    public function setNombreRazon(?string $nombreRazon): self
    {
        $this->nombreRazon = $nombreRazon;
        return $this;
    }

    public function getApellidos(): ?string
    {
        return $this->apellidos;
    }

    public function setApellidos(?string $apellidos): self
    {
        $this->apellidos = $apellidos;
        return $this;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(?string $nombre): self
    {
        $this->nombre = $nombre;
        return $this;
    }

    public function getRazonSocial(): ?string
    {
        return $this->razonSocial;
    }

    public function setRazonSocial(?string $razonSocial): self
    {
        $this->razonSocial = $razonSocial;
        return $this;
    }

    public function getTipoIdentificacion(): ?string
    {
        return $this->tipoIdentificacion;
    }

    public function setTipoIdentificacion(?string $tipoIdentificacion): self
    {
        $this->tipoIdentificacion = $tipoIdentificacion;
        return $this;
    }

    public function getIdOtro(): IDOtro
    {
        return $this->idOtro;
    }

    public function setIdOtro(IDOtro $idOtro): self
    {
        $this->idOtro = $idOtro;
        return $this;
    }

    public function getPais(): ?string
    {
        return $this->pais;
    }

    public function setPais(?string $pais): self
    {
        $this->pais = $pais;
        return $this;
    }

    public function toXml(\DOMDocument $doc): \DOMElement
    {
        $root = $this->createElement($doc, 'PersonaFisicaJuridica');

        if ($this->nif !== null) {
            $root->appendChild($this->createElement($doc, 'NIF', $this->nif));
        }

        if ($this->nombreRazon !== null) {
            $root->appendChild($this->createElement($doc, 'NombreRazon', $this->nombreRazon));
        }

        if ($this->apellidos !== null) {
            $root->appendChild($this->createElement($doc, 'Apellidos', $this->apellidos));
        }

        if ($this->nombre !== null) {
            $root->appendChild($this->createElement($doc, 'Nombre', $this->nombre));
        }

        if ($this->razonSocial !== null) {
            $root->appendChild($this->createElement($doc, 'RazonSocial', $this->razonSocial));
        }

        if ($this->tipoIdentificacion !== null) {
            $root->appendChild($this->createElement($doc, 'TipoIdentificacion', $this->tipoIdentificacion));
        }

        if ($this->idOtro !== null) {
            $root->appendChild($this->idOtro->toXml($doc));
        }

        if ($this->pais !== null) {
            $root->appendChild($this->createElement($doc, 'Pais', $this->pais));
        }

        return $root;
    }

    /**
     * Create a PersonaFisicaJuridica instance from XML string or DOMElement
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
        $persona = new self();

        $nifElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'NIF')->item(0);
        if ($nifElement) {
            $persona->setNif($nifElement->nodeValue);
        }

        $nombreRazonElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'NombreRazon')->item(0);
        if ($nombreRazonElement) {
            $persona->setNombreRazon($nombreRazonElement->nodeValue);
        }

        $apellidosElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'Apellidos')->item(0);
        if ($apellidosElement) {
            $persona->setApellidos($apellidosElement->nodeValue);
        }

        $nombreElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'Nombre')->item(0);
        if ($nombreElement) {
            $persona->setNombre($nombreElement->nodeValue);
        }

        $razonSocialElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'RazonSocial')->item(0);
        if ($razonSocialElement) {
            $persona->setRazonSocial($razonSocialElement->nodeValue);
        }

        $tipoIdentificacionElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'TipoIdentificacion')->item(0);
        if ($tipoIdentificacionElement) {
            $persona->setTipoIdentificacion($tipoIdentificacionElement->nodeValue);
        }

        $idOtroElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'IDOtro')->item(0);
        if ($idOtroElement) {
            $persona->setIdOtro($idOtroElement->nodeValue);
        }

        $paisElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'Pais')->item(0);
        if ($paisElement) {
            $persona->setPais($paisElement->nodeValue);
        }

        return $persona;
    }
}
