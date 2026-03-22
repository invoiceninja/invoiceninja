<?php

namespace App\Services\EDocument\Standards\Verifactu\Models;

/**
 * RegistroAnulacion - Invoice Cancellation Record
 *
 * This class represents the cancellation record information required for Verifactu e-invoicing
 * modification operations. It contains the details of the invoice to be cancelled.
 */
class RegistroAnulacion extends BaseXmlModel
{
    protected string $idVersion;
    protected string $idEmisorFactura;
    protected string $numSerieFactura;
    protected string $fechaExpedicionFactura;
    protected string $motivoAnulacion;
    protected string $nombreRazonEmisor;
    // Additional properties required by XSD schema
    protected ?string $refExterna = null;
    protected ?string $sinRegistroPrevio = null;
    protected ?string $rechazoPrevio = null;
    protected ?string $generadoPor = null;
    protected ?PersonaFisicaJuridica $generador = null;
    protected Encadenamiento $encadenamiento;
    protected SistemaInformatico $sistemaInformatico;
    protected string $fechaHoraHusoGenRegistro;
    protected string $tipoHuella;
    protected string $huella;
    protected ?string $signature = null;

    public function __construct()
    {
        $this->idVersion = '1.0';
        $this->motivoAnulacion = '1'; // Default: Sustitución por otra factura
        $this->encadenamiento = new Encadenamiento();
        $this->sistemaInformatico = new SistemaInformatico();
        $this->fechaHoraHusoGenRegistro = now()->format('Y-m-d\TH:i:sP');
        $this->tipoHuella = '01';
        $this->huella = '';
    }

    public function getIdVersion(): string
    {
        return $this->idVersion;
    }

    public function setIdVersion(string $idVersion): self
    {
        $this->idVersion = $idVersion;
        return $this;
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

    public function getMotivoAnulacion(): string
    {
        return $this->motivoAnulacion;
    }

    public function setMotivoAnulacion(string $motivoAnulacion): self
    {
        $this->motivoAnulacion = $motivoAnulacion;
        return $this;
    }

    public function getRefExterna(): ?string
    {
        return $this->refExterna;
    }

    public function setRefExterna(?string $refExterna): self
    {
        $this->refExterna = $refExterna;
        return $this;
    }

    public function getSinRegistroPrevio(): ?string
    {
        return $this->sinRegistroPrevio;
    }

    public function setSinRegistroPrevio(?string $sinRegistroPrevio): self
    {
        $this->sinRegistroPrevio = $sinRegistroPrevio;
        return $this;
    }

    public function getRechazoPrevio(): ?string
    {
        return $this->rechazoPrevio;
    }

    public function setRechazoPrevio(?string $rechazoPrevio): self
    {
        $this->rechazoPrevio = $rechazoPrevio;
        return $this;
    }

    public function getGeneradoPor(): ?string
    {
        return $this->generadoPor;
    }

    public function setGeneradoPor(?string $generadoPor): self
    {
        $this->generadoPor = $generadoPor;
        return $this;
    }

    public function getGenerador(): ?PersonaFisicaJuridica
    {
        return $this->generador;
    }

    public function setGenerador(?PersonaFisicaJuridica $generador): self
    {
        $this->generador = $generador;
        return $this;
    }

    public function getEncadenamiento(): Encadenamiento
    {
        return $this->encadenamiento;
    }

    public function setEncadenamiento(Encadenamiento $encadenamiento): self
    {
        $this->encadenamiento = $encadenamiento;
        return $this;
    }

    public function getSistemaInformatico(): SistemaInformatico
    {
        return $this->sistemaInformatico;
    }

    public function setSistemaInformatico(SistemaInformatico $sistemaInformatico): self
    {
        $this->sistemaInformatico = $sistemaInformatico;
        return $this;
    }

    public function getFechaHoraHusoGenRegistro(): string
    {
        return $this->fechaHoraHusoGenRegistro;
    }

    public function setFechaHoraHusoGenRegistro(string $fechaHoraHusoGenRegistro): self
    {
        $this->fechaHoraHusoGenRegistro = $fechaHoraHusoGenRegistro;
        return $this;
    }

    public function getTipoHuella(): string
    {
        return $this->tipoHuella;
    }

    public function setTipoHuella(string $tipoHuella): self
    {
        $this->tipoHuella = $tipoHuella;
        return $this;
    }

    public function getHuella(): string
    {
        return $this->huella;
    }

    public function setHuella(string $huella): self
    {
        $this->huella = $huella;
        return $this;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }

    public function setSignature(?string $signature): self
    {
        $this->signature = $signature;
        return $this;
    }

    public function getNombreRazonEmisor(): string
    {
        return $this->nombreRazonEmisor;
    }

    public function setNombreRazonEmisor(string $nombreRazonEmisor): self
    {
        $this->nombreRazonEmisor = $nombreRazonEmisor;
        return $this;
    }

    public function toXml(\DOMDocument $doc): \DOMElement
    {
        $root = $doc->createElementNS(self::XML_NAMESPACE, self::XML_NAMESPACE_PREFIX . ':RegistroAnulacion');

        // Add IDVersion
        $root->appendChild($this->createElement($doc, 'IDVersion', $this->idVersion));

        // Create IDFactura structure
        $idFactura = $this->createElement($doc, 'IDFactura');
        $idFactura->appendChild($this->createElement($doc, 'IDEmisorFacturaAnulada', $this->idEmisorFactura));
        $idFactura->appendChild($this->createElement($doc, 'NumSerieFacturaAnulada', $this->numSerieFactura));
        $idFactura->appendChild($this->createElement($doc, 'FechaExpedicionFacturaAnulada', $this->fechaExpedicionFactura));
        $root->appendChild($idFactura);

        // Add optional RefExterna
        if ($this->refExterna !== null) {
            $root->appendChild($this->createElement($doc, 'RefExterna', $this->refExterna));
        }

        // Add optional SinRegistroPrevio
        if ($this->sinRegistroPrevio !== null) {
            $root->appendChild($this->createElement($doc, 'SinRegistroPrevio', $this->sinRegistroPrevio));
        }

        // Add optional RechazoPrevio
        if ($this->rechazoPrevio !== null) {
            $root->appendChild($this->createElement($doc, 'RechazoPrevio', $this->rechazoPrevio));
        }

        // Add optional GeneradoPor
        if ($this->generadoPor !== null) {
            $root->appendChild($this->createElement($doc, 'GeneradoPor', $this->generadoPor));
        }

        // Add optional Generador
        if ($this->generador !== null) {
            $root->appendChild($this->generador->toXml($doc));
        }

        // Add Encadenamiento using actual property
        $encadenamientoElement = $this->encadenamiento->toXml($doc);
        $root->appendChild($encadenamientoElement);

        // Add SistemaInformatico using actual property
        $sistemaInformaticoElement = $this->sistemaInformatico->toXml($doc);
        $root->appendChild($sistemaInformaticoElement);

        // Add FechaHoraHusoGenRegistro using actual property
        $root->appendChild($this->createElement($doc, 'FechaHoraHusoGenRegistro', $this->fechaHoraHusoGenRegistro));

        // Add TipoHuella using actual property
        $root->appendChild($this->createElement($doc, 'TipoHuella', $this->tipoHuella));

        // Add Huella using actual property
        $root->appendChild($this->createElement($doc, 'Huella', $this->huella));

        // Add optional Signature
        if ($this->signature !== null) {
            $root->appendChild($this->createDsElement($doc, 'Signature', $this->signature));
        }

        return $root;
    }

    public static function fromDOMElement(\DOMElement $element): self
    {
        $registroAnulacion = new self();

        // Handle IDVersion
        $idVersion = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'IDVersion')->item(0);
        if ($idVersion) {
            $registroAnulacion->setIdVersion($idVersion->nodeValue);
        }

        // Handle IDFactura
        $idFactura = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'IDFactura')->item(0);
        if ($idFactura) {
            $idEmisorFactura = $idFactura->getElementsByTagNameNS(self::XML_NAMESPACE, 'IDEmisorFacturaAnulada')->item(0);
            if ($idEmisorFactura) {
                $registroAnulacion->setIdEmisorFactura($idEmisorFactura->nodeValue);
            }

            $numSerieFactura = $idFactura->getElementsByTagNameNS(self::XML_NAMESPACE, 'NumSerieFacturaAnulada')->item(0);
            if ($numSerieFactura) {
                $registroAnulacion->setNumSerieFactura($numSerieFactura->nodeValue);
            }

            $fechaExpedicionFactura = $idFactura->getElementsByTagNameNS(self::XML_NAMESPACE, 'FechaExpedicionFacturaAnulada')->item(0);
            if ($fechaExpedicionFactura) {
                $registroAnulacion->setFechaExpedicionFactura($fechaExpedicionFactura->nodeValue);
            }
        }

        // Handle optional elements
        $refExterna = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'RefExterna')->item(0);
        if ($refExterna) {
            $registroAnulacion->setRefExterna($refExterna->nodeValue);
        }

        $sinRegistroPrevio = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'SinRegistroPrevio')->item(0);
        if ($sinRegistroPrevio) {
            $registroAnulacion->setSinRegistroPrevio($sinRegistroPrevio->nodeValue);
        }

        $rechazoPrevio = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'RechazoPrevio')->item(0);
        if ($rechazoPrevio) {
            $registroAnulacion->setRechazoPrevio($rechazoPrevio->nodeValue);
        }

        $generadoPor = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'GeneradoPor')->item(0);
        if ($generadoPor) {
            $registroAnulacion->setGeneradoPor($generadoPor->nodeValue);
        }

        // Handle Generador
        $generador = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'Generador')->item(0);
        if ($generador) {
            $registroAnulacion->setGenerador(PersonaFisicaJuridica::fromDOMElement($generador));
        }

        // Handle Encadenamiento
        $encadenamiento = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'Encadenamiento')->item(0);
        if ($encadenamiento) {
            $registroAnulacion->setEncadenamiento(Encadenamiento::fromDOMElement($encadenamiento));
        }

        // Handle SistemaInformatico
        $sistemaInformatico = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'SistemaInformatico')->item(0);
        if ($sistemaInformatico) {
            $registroAnulacion->setSistemaInformatico(SistemaInformatico::fromDOMElement($sistemaInformatico));
        }

        // Handle FechaHoraHusoGenRegistro
        $fechaHoraHusoGenRegistro = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'FechaHoraHusoGenRegistro')->item(0);
        if ($fechaHoraHusoGenRegistro) {
            $registroAnulacion->setFechaHoraHusoGenRegistro($fechaHoraHusoGenRegistro->nodeValue);
        }

        // Handle TipoHuella
        $tipoHuella = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'TipoHuella')->item(0);
        if ($tipoHuella) {
            $registroAnulacion->setTipoHuella($tipoHuella->nodeValue);
        }

        // Handle Huella
        $huella = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'Huella')->item(0);
        if ($huella) {
            $registroAnulacion->setHuella($huella->nodeValue);
        }

        // Handle MotivoAnulacion
        $motivoAnulacion = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'MotivoAnulacion')->item(0);
        if ($motivoAnulacion) {
            $registroAnulacion->setMotivoAnulacion($motivoAnulacion->nodeValue);
        }

        return $registroAnulacion;
    }

    public function toXmlString(): string
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $root = $this->toXml($doc);
        $doc->appendChild($root);

        return $doc->saveXML();
    }

    public function toSoapEnvelope(): string
    {
        // Create the SOAP document
        $soapDoc = new \DOMDocument('1.0', 'UTF-8');
        $soapDoc->preserveWhiteSpace = false;
        $soapDoc->formatOutput = true;

        // Create SOAP envelope with namespaces
        $envelope = $soapDoc->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soapenv:Envelope');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:soapenv', 'http://schemas.xmlsoap.org/soap/envelope/');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sum', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sum1', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd');

        $soapDoc->appendChild($envelope);

        // Create Header
        $header = $soapDoc->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soapenv:Header');
        $envelope->appendChild($header);

        // Create Body
        $body = $soapDoc->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soapenv:Body');
        $envelope->appendChild($body);

        // Create RegFactuSistemaFacturacion
        $regFactu = $soapDoc->createElementNS('https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd', 'sum:RegFactuSistemaFacturacion');
        $body->appendChild($regFactu);

        // Create Cabecera
        $cabecera = $soapDoc->createElementNS('https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd', 'sum:Cabecera');
        $regFactu->appendChild($cabecera);

        // Create ObligadoEmision
        $obligadoEmision = $soapDoc->createElementNS('https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd', 'sum1:ObligadoEmision');
        $cabecera->appendChild($obligadoEmision);

        // Add ObligadoEmision content (using default values for now)
        $obligadoEmision->appendChild($soapDoc->createElementNS('https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd', 'sum1:NombreRazon', $this->getNombreRazonEmisor()));
        $obligadoEmision->appendChild($soapDoc->createElementNS('https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd', 'sum1:NIF', $this->getIdEmisorFactura()));

        // Create RegistroFactura
        $registroFactura = $soapDoc->createElementNS('https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd', 'sum:RegistroFactura');
        $regFactu->appendChild($registroFactura);

        // Import your existing XML into the RegistroFactura
        $yourXmlDoc = new \DOMDocument();
        $yourXmlDoc->loadXML($this->toXmlString());

        // Import the root element from your XML
        $importedNode = $soapDoc->importNode($yourXmlDoc->documentElement, true);
        $registroFactura->appendChild($importedNode);

        return $soapDoc->saveXML();
    }
}
