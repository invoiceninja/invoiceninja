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

use Illuminate\Support\Facades\Log;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use App\Services\EDocument\Standards\Verifactu\Models\IDOtro;

class Invoice extends BaseXmlModel implements XmlModelInterface
{
    // Constants for invoice types
    public const TIPO_FACTURA_NORMAL = 'F1';
    public const TIPO_FACTURA_RECTIFICATIVA_PARTIAL = 'R1';
    public const TIPO_FACTURA_RECTIFICATIVA = 'R2';
    public const TIPO_FACTURA_SUSTITUIDA = 'F3';

    // Constants for rectification types
    public const TIPO_RECTIFICATIVA_COMPLETA = 'I';      // Rectificación por diferencias (Complete rectification)
    public const TIPO_RECTIFICATIVA_SUSTITUTIVA = 'S';   // Rectificación sustitutiva (Substitutive rectification)

    protected string $idVersion;
    protected IDFactura $idFactura;
    protected ?string $refExterna = null;
    protected string $nombreRazonEmisor;
    protected ?string $subsanacion = null;
    protected ?string $rechazoPrevio = null;
    protected string $tipoFactura;
    protected ?string $tipoRectificativa = null;
    protected ?array $facturasRectificadas = null;
    protected ?array $facturasSustituidas = null;
    protected ?array $importeRectificacion = null;
    protected ?string $fechaOperacion = null;
    protected string $descripcionOperacion;
    protected ?string $facturaSimplificadaArt7273 = null;
    protected ?string $facturaSinIdentifDestinatarioArt61d = null;
    protected ?string $macrodato = null;
    protected ?string $emitidaPorTerceroODestinatario = null;
    protected ?PersonaFisicaJuridica $tercero = null;
    protected ?array $destinatarios = null;
    protected ?string $cupon = null;
    protected Desglose $desglose;
    protected float $cuotaTotal;
    protected float $importeTotal;
    protected Encadenamiento $encadenamiento;
    protected SistemaInformatico $sistemaInformatico;
    protected string $fechaHoraHusoGenRegistro;
    protected ?string $numRegistroAcuerdoFacturacion = null;
    protected ?string $idAcuerdoSistemaInformatico = null;
    protected string $tipoHuella;
    protected string $huella;
    protected ?string $signature = null;
    protected ?string $privateKeyPath = null;
    protected ?string $publicKeyPath = null;
    protected ?string $certificatePath = null;
    protected ?string $fechaExpedicionFactura = null;

    public function __construct()
    {
        // Initialize required properties
        $this->desglose = new Desglose();
        $this->encadenamiento = new Encadenamiento();
        $this->sistemaInformatico = new SistemaInformatico();
        $this->idFactura = new IDFactura();
        $this->tipoFactura = self::TIPO_FACTURA_NORMAL; // Default to normal invoice
    }

    // Getters and setters for all properties
    public function getIdVersion(): string
    {
        return $this->idVersion;
    }

    public function setIdVersion(string $idVersion): self
    {
        $this->idVersion = $idVersion;
        return $this;
    }

    public function getFechaExpedicionFactura(): string
    {
        return $this->fechaExpedicionFactura ?? now()->format('d-m-Y');
    }

    public function setFechaExpedicionFactura(string $fechaExpedicionFactura): self
    {
        $this->fechaExpedicionFactura = $fechaExpedicionFactura;
        return $this;
    }

    public function getIdFactura(): IDFactura
    {
        return $this->idFactura;
    }

    public function setIdFactura(IDFactura $idFactura): self
    {
        $this->idFactura = $idFactura;
        return $this;
    }

    // Convenience methods for backward compatibility
    public function getNumSerieFactura(): string
    {
        return $this->idFactura->getNumSerieFactura();
    }

    public function setNumSerieFactura(string $numSerieFactura): self
    {
        $this->idFactura->setNumSerieFactura($numSerieFactura);
        return $this;
    }

    public function getIdEmisorFactura(): string
    {
        return $this->idFactura->getIdEmisorFactura();
    }

    public function setIdEmisorFactura(string $idEmisorFactura): self
    {
        $this->idFactura->setIdEmisorFactura($idEmisorFactura);
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

    public function getNombreRazonEmisor(): string
    {
        return $this->nombreRazonEmisor;
    }

    public function setNombreRazonEmisor(string $nombreRazonEmisor): self
    {
        $this->nombreRazonEmisor = $nombreRazonEmisor;
        return $this;
    }

    public function getSubsanacion(): ?string
    {
        return $this->subsanacion;
    }

    public function setSubsanacion(?string $subsanacion): self
    {
        $this->subsanacion = $subsanacion;
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

    public function getTipoFactura(): string
    {
        return $this->tipoFactura;
    }

    public function setTipoFactura(string $tipoFactura): self
    {
        $validTypes = ['F1', 'F2', 'F3', 'R1', 'R2', 'R3', 'R4', 'R5'];
        if (!in_array($tipoFactura, $validTypes, true)) {
            throw new \InvalidArgumentException('Invalid TipoFactura value. Must be one of: ' . implode(', ', $validTypes));
        }
        $this->tipoFactura = $tipoFactura;
        return $this;
    }

    public function getTipoRectificativa(): ?string
    {
        return $this->tipoRectificativa;
    }

    public function setTipoRectificativa(?string $tipoRectificativa): self
    {
        if ($tipoRectificativa !== null) {
            $validTypes = ['S', 'I'];
            if (!in_array($tipoRectificativa, $validTypes, true)) {
                throw new \InvalidArgumentException('Invalid TipoRectificativa value. Must be one of: ' . implode(', ', $validTypes));
            }
        }
        $this->tipoRectificativa = $tipoRectificativa;
        return $this;
    }

    public function getFacturasRectificadas(): ?array
    {
        return $this->facturasRectificadas;
    }

    public function setFacturasRectificadas(?array $facturasRectificadas): self
    {
        $this->facturasRectificadas = $facturasRectificadas;
        return $this;
    }

    public function getFacturasSustituidas(): ?array
    {
        return $this->facturasSustituidas;
    }

    public function setFacturasSustituidas(?array $facturasSustituidas): self
    {
        $this->facturasSustituidas = $facturasSustituidas;
        return $this;
    }

    public function getImporteRectificacion(): ?array
    {
        return $this->importeRectificacion;
    }

    public function setImporteRectificacion(?array $importeRectificacion): self
    {

        $this->importeRectificacion = $importeRectificacion;

        return $this;
    }

    public function setRectificationAmounts(array $amounts): self
    {

        $this->importeRectificacion = $amounts;
        return $this;
    }

    public function getFechaOperacion(): ?string
    {
        return $this->fechaOperacion;
    }

    public function setFechaOperacion(?string $fechaOperacion): self
    {
        $this->fechaOperacion = $fechaOperacion;
        return $this;
    }

    public function getDescripcionOperacion(): string
    {
        return $this->descripcionOperacion;
    }

    public function setDescripcionOperacion(string $descripcionOperacion): self
    {
        $this->descripcionOperacion = $descripcionOperacion;
        return $this;
    }

    public function getFacturaSimplificadaArt7273(): ?string
    {
        return $this->facturaSimplificadaArt7273;
    }

    public function setFacturaSimplificadaArt7273(?string $facturaSimplificadaArt7273): self
    {
        $this->facturaSimplificadaArt7273 = $facturaSimplificadaArt7273;
        return $this;
    }

    public function getFacturaSinIdentifDestinatarioArt61d(): ?string
    {
        return $this->facturaSinIdentifDestinatarioArt61d;
    }

    public function setFacturaSinIdentifDestinatarioArt61d(?string $facturaSinIdentifDestinatarioArt61d): self
    {
        $this->facturaSinIdentifDestinatarioArt61d = $facturaSinIdentifDestinatarioArt61d;
        return $this;
    }

    public function getMacrodato(): ?string
    {
        return $this->macrodato;
    }

    public function setMacrodato(?string $macrodato): self
    {
        $this->macrodato = $macrodato;
        return $this;
    }

    public function getEmitidaPorTerceroODestinatario(): ?string
    {
        return $this->emitidaPorTerceroODestinatario;
    }

    public function setEmitidaPorTerceroODestinatario(?string $emitidaPorTerceroODestinatario): self
    {
        $this->emitidaPorTerceroODestinatario = $emitidaPorTerceroODestinatario;
        return $this;
    }

    public function getTercero(): ?PersonaFisicaJuridica
    {
        return $this->tercero;
    }

    public function setTercero(?PersonaFisicaJuridica $tercero): self
    {
        $this->tercero = $tercero;
        return $this;
    }



    public function getDestinatarios(): ?array
    {
        return $this->destinatarios;
    }

    public function setDestinatarios(?array $destinatarios): self
    {
        if ($destinatarios !== null && count($destinatarios) > 1000) {
            throw new \InvalidArgumentException('Maximum number of recipients (1000) exceeded');
        }

        // Ensure all elements are PersonaFisicaJuridica instances
        if ($destinatarios !== null) {
            foreach ($destinatarios as $destinatario) {
                if (!($destinatario instanceof PersonaFisicaJuridica || $destinatario instanceof IDOtro)) {
                    throw new \InvalidArgumentException('All recipients must be instances of PersonaFisicaJuridica');
                }
            }
        }

        $this->destinatarios = $destinatarios;
        return $this;
    }

    public function getCupon(): ?string
    {
        return $this->cupon;
    }

    public function setCupon(?string $cupon): self
    {
        if ($cupon !== null && !in_array($cupon, ['S', 'N'])) {
            throw new \InvalidArgumentException('Cupon must be either "S" or "N"');
        }
        $this->cupon = $cupon;
        return $this;
    }

    public function getDesglose(): Desglose
    {
        return $this->desglose;
    }

    public function setDesglose(Desglose $desglose): self
    {
        $this->desglose = $desglose;
        return $this;
    }

    public function getCuotaTotal(): float
    {
        return $this->cuotaTotal;
    }

    public function setCuotaTotal(float $cuotaTotal): self
    {
        $this->cuotaTotal = $cuotaTotal;
        return $this;
    }

    public function getImporteTotal(): float
    {
        return $this->importeTotal;
    }

    public function setImporteTotal($importeTotal): self
    {
        if (!is_numeric($importeTotal)) {
            throw new \InvalidArgumentException('ImporteTotal must be a numeric value');
        }

        $formatted = number_format((float)$importeTotal, 2, '.', '');
        if (!preg_match('/^(\+|-)?\d{1,12}(\.\d{0,2})?$/', $formatted)) {
            throw new \InvalidArgumentException('ImporteTotal must be a number with up to 12 digits and 2 decimal places');
        }

        $this->importeTotal = (float)$importeTotal;
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
        // if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $fechaHoraHusoGenRegistro)) {
        //     throw new \InvalidArgumentException('Invalid date format for FechaHoraHusoGenRegistro. Expected format: YYYY-MM-DDThh:mm:ss');
        // }
        $this->fechaHoraHusoGenRegistro = $fechaHoraHusoGenRegistro;
        return $this;
    }

    public function getNumRegistroAcuerdoFacturacion(): ?string
    {
        return $this->numRegistroAcuerdoFacturacion;
    }

    public function setNumRegistroAcuerdoFacturacion(?string $numRegistroAcuerdoFacturacion): self
    {
        $this->numRegistroAcuerdoFacturacion = $numRegistroAcuerdoFacturacion;
        return $this;
    }

    public function getIdAcuerdoSistemaInformatico(): ?string
    {
        return $this->idAcuerdoSistemaInformatico;
    }

    public function setIdAcuerdoSistemaInformatico(?string $idAcuerdoSistemaInformatico): self
    {
        $this->idAcuerdoSistemaInformatico = $idAcuerdoSistemaInformatico;
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

    /**
     * Helper method to create a rectificative invoice with proper configuration
     *
     * @param string $tipoRectificativa The type of rectification ('I' for complete, 'S' for substitutive)
     * @param string $descripcionOperacion Description of the rectification operation
     * @return self
     */
    public function makeRectificative(string $tipoRectificativa, string $descripcionOperacion = 'Rectificación de factura'): self
    {
        $this->setTipoFactura(self::TIPO_FACTURA_RECTIFICATIVA)
             ->setTipoRectificativa($tipoRectificativa)
             ->setDescripcionOperacion($descripcionOperacion);

        return $this;
    }

    /**
     * Helper method to create a complete rectification invoice
     *
     * @param string $descripcionOperacion Description of the rectification operation
     * @return self
     */
    public function makeCompleteRectification(string $descripcionOperacion = 'Rectificación completa de factura'): self
    {
        return $this->makeRectificative(self::TIPO_RECTIFICATIVA_COMPLETA, $descripcionOperacion);
    }

    /**
     * Helper method to create a substitutive rectification invoice
     *
     * @param string $descripcionOperacion Description of the rectification operation
     * @return self
     */
    public function makeSubstitutiveRectification(string $descripcionOperacion = 'Rectificación sustitutiva de factura'): self
    {
        // For substitutive rectifications, we need to ensure ImporteRectificacion is set
        // This method will throw an error if ImporteRectificacion is not set
        $this->setTipoFactura(self::TIPO_FACTURA_RECTIFICATIVA)
             ->setTipoRectificativa(self::TIPO_RECTIFICATIVA_SUSTITUTIVA)
             ->setDescripcionOperacion($descripcionOperacion);

        // Validate that ImporteRectificacion is set for substitutive rectifications
        if ($this->importeRectificacion === null) {
            throw new \InvalidArgumentException('ImporteRectificacion must be set for substitutive rectifications. Use makeSubstitutiveRectificationWithAmount() or setImporteRectificacion() before calling this method.');
        }

        return $this;
    }

    /**
     * Helper method to create a rectificative invoice with ImporteRectificacion
     *
     * @param string $tipoRectificativa The type of rectification ('I' for complete, 'S' for substitutive)
     * @param array $importeRectificacion The rectification amount
     * @param string $descripcionOperacion Description of the rectification operation
     * @return self
     */
    public function makeRectificativeWithAmount(string $tipoRectificativa, array $importeRectificacion, string $descripcionOperacion = 'Rectificación de factura'): self
    {
        $this->setTipoFactura(self::TIPO_FACTURA_RECTIFICATIVA)
             ->setTipoRectificativa($tipoRectificativa)
             ->setImporteRectificacion($importeRectificacion)
             ->setDescripcionOperacion($descripcionOperacion);

        return $this;
    }


    /**
     * Validate that the invoice is properly configured for its type
     *
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function validate(): bool
    {
        // Basic validation for all invoice types
        if (empty($this->idVersion)) {
            throw new \InvalidArgumentException('Missing required field: IDVersion');
        }

        if (empty($this->nombreRazonEmisor)) {
            throw new \InvalidArgumentException('Missing required field: NombreRazonEmisor');
        }

        if (empty($this->descripcionOperacion)) {
            throw new \InvalidArgumentException('Missing required field: DescripcionOperacion');
        }

        if (!in_array($this->tipoFactura, [self::TIPO_FACTURA_RECTIFICATIVA_PARTIAL, self::TIPO_FACTURA_RECTIFICATIVA]) && $this->cuotaTotal < 0) {
            throw new \InvalidArgumentException('Missing required field: CuotaTotal');
        }

        if (!in_array($this->tipoFactura, [self::TIPO_FACTURA_RECTIFICATIVA_PARTIAL, self::TIPO_FACTURA_RECTIFICATIVA]) && $this->importeTotal < 0) {
            throw new \InvalidArgumentException('Missing required field: ImporteTotal');
        }

        // Specific validation for R1 invoices
        if (in_array($this->tipoFactura, [self::TIPO_FACTURA_RECTIFICATIVA_PARTIAL, self::TIPO_FACTURA_RECTIFICATIVA])) {
            if ($this->tipoRectificativa === null) {
                throw new \InvalidArgumentException('Missing required field: TipoRectificativa');
            }

            if (!in_array($this->tipoRectificativa, [self::TIPO_RECTIFICATIVA_COMPLETA, self::TIPO_RECTIFICATIVA_SUSTITUTIVA])) {
                throw new \InvalidArgumentException('TipoRectificativa must be either "I" (complete) or "S" (substitutive)');
            }

            // For substitutive rectifications, ImporteRectificacion is mandatory
            if ($this->tipoRectificativa === self::TIPO_RECTIFICATIVA_SUSTITUTIVA && $this->importeRectificacion === null) {
                throw new \InvalidArgumentException('Missing required field: ImporteRectificacion');
            }
        }

        return true;
    }

    public function setPrivateKeyPath(string $path): self
    {
        $this->privateKeyPath = $path;
        return $this;
    }

    public function setPublicKeyPath(string $path): self
    {
        $this->publicKeyPath = $path;
        return $this;
    }

    public function setCertificatePath(string $path): self
    {
        $this->certificatePath = $path;
        return $this;
    }

    public function signXml(\DOMDocument $doc): void
    {
        if (!file_exists($this->certificatePath)) {
            throw new \RuntimeException("Certificate file not found at: " . $this->certificatePath);
        }
        if (!file_exists($this->privateKeyPath)) {
            throw new \RuntimeException("Private key file not found at: " . $this->privateKeyPath);
        }

        try {
            $xmlContent = $doc->saveXML();
            Log::debug("XML before signing:", ['xml' => $xmlContent]);

            $objDSig = new XMLSecurityDSig(); //@phpstan-ignore-line
            $objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N); //@phpstan-ignore-line

            // Create a new security key
            $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, array('type' => 'private')); //@phpstan-ignore-line

            // Load the private key
            $objKey->loadKey($this->privateKeyPath, true);

            // Add the reference
            $objDSig->addReference(
                $doc,
                XMLSecurityDSig::SHA256, //@phpstan-ignore-line
                [
                    'http://www.w3.org/2000/09/xmldsig#enveloped-signature',
                    'http://www.w3.org/2001/10/xml-exc-c14n#'
                ],
                ['force_uri' => true]
            );

            // Add the certificate to the security object
            $objDSig->add509Cert(file_get_contents($this->certificatePath));

            // Append the signature
            $objDSig->sign($objKey);

            // Append the signature to the XML
            $objDSig->appendSignature($doc->documentElement);

            // Verify the signature
            $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, array('type' => 'public')); //@phpstan-ignore-line
            $objKey->loadKey($this->publicKeyPath, true, true);

            if ($objDSig->verify($objKey) === 1) {
                Log::debug("Signature verification successful");
            } else {
                Log::error("Signature verification failed");
            }

            $xmlContent = $doc->saveXML();
            Log::debug("XML after signing:", ['xml' => $xmlContent]);

        } catch (\Exception $e) {
            Log::error("Error during XML signing: " . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function verifySignature(\DOMDocument $doc): bool
    {
        if (!$this->publicKeyPath || !file_exists($this->publicKeyPath)) {
            throw new \RuntimeException('Public key not found or not set');
        }

        try {
            Log::info('Starting signature verification');
            Log::debug('XML to verify: ' . $doc->saveXML());

            // Get the signature node
            $objXMLSecDSig = new XMLSecurityDSig(); //@phpstan-ignore-line

            // Locate the signature
            Log::debug('Locating signature');
            $objDSig = $objXMLSecDSig->locateSignature($doc);
            if (!$objDSig) {
                throw new \RuntimeException('Signature not found in document');
            }

            // Canonicalize the signed info
            Log::debug('Canonicalizing SignedInfo');
            $objXMLSecDSig->canonicalizeSignedInfo();

            // Validate references
            Log::debug('Validating references');
            try {
                $objXMLSecDSig->validateReference();
            } catch (\Exception $e) {
                Log::error('Reference validation failed: ' . $e->getMessage());
                throw $e;
            }

            // Get the key from the certificate
            Log::debug('Locating key');
            $objKey = $objXMLSecDSig->locateKey();
            if (!$objKey) {
                throw new \RuntimeException('Key not found in signature');
            }

            // Load the public key
            Log::debug('Loading public key from: ' . $this->publicKeyPath);
            $objKey->loadKey($this->publicKeyPath, false, true);

            // Verify the signature
            Log::debug('Verifying signature');
            $result = $objXMLSecDSig->verify($objKey) === 1;

            Log::info('Signature verification ' . ($result ? 'succeeded' : 'failed'));
            return $result;
        } catch (\Exception $e) {
            Log::error('Error during signature verification: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    public function toXml(\DOMDocument $doc): \DOMElement
    {
        // Create root element with proper namespaces
        $root = $doc->createElementNS(parent::XML_NAMESPACE, parent::XML_NAMESPACE_PREFIX . ':RegistroAlta');

        // Add namespaces
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . parent::XML_NAMESPACE_PREFIX, parent::XML_NAMESPACE);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . parent::XML_DS_NAMESPACE_PREFIX, parent::XML_DS_NAMESPACE);

        // Add required elements in EXACT order according to the expected XML structure for R1 invoices

        // 1. IDVersion
        $root->appendChild($this->createElement($doc, 'IDVersion', $this->idVersion));

        // 2. IDFactura using the complex object
        $root->appendChild($this->idFactura->toXml($doc));

        // 3. NombreRazonEmisor
        $root->appendChild($this->createElement($doc, 'NombreRazonEmisor', $this->nombreRazonEmisor));

        // 4. TipoFactura
        $root->appendChild($this->createElement($doc, 'TipoFactura', $this->tipoFactura));

        // 5. TipoRectificativa (only for R1 invoices)
        if (in_array($this->tipoFactura, [self::TIPO_FACTURA_SUSTITUIDA, self::TIPO_FACTURA_RECTIFICATIVA, self::TIPO_FACTURA_RECTIFICATIVA_PARTIAL]) && $this->tipoRectificativa !== null) {
            $root->appendChild($this->createElement($doc, 'TipoRectificativa', $this->tipoRectificativa));
        }

        // 6. FacturasRectificadas (only for R1 invoices)
        if (in_array($this->tipoFactura, [self::TIPO_FACTURA_RECTIFICATIVA_PARTIAL, self::TIPO_FACTURA_RECTIFICATIVA]) && $this->facturasRectificadas !== null) {
            $facturasRectificadasElement = $this->createElement($doc, 'FacturasRectificadas');

            foreach ($this->facturasRectificadas as $facturaRectificada) {
                $idFacturaRectificadaElement = $this->createElement($doc, 'IDFacturaRectificada');

                // Add IDEmisorFactura
                $idFacturaRectificadaElement->appendChild($this->createElement($doc, 'IDEmisorFactura', $facturaRectificada['IDEmisorFactura']));

                // Add NumSerieFactura
                $idFacturaRectificadaElement->appendChild($this->createElement($doc, 'NumSerieFactura', $facturaRectificada['NumSerieFactura']));

                // Add FechaExpedicionFactura
                $idFacturaRectificadaElement->appendChild($this->createElement($doc, 'FechaExpedicionFactura', $facturaRectificada['FechaExpedicionFactura']));

                $facturasRectificadasElement->appendChild($idFacturaRectificadaElement);
            }

            $root->appendChild($facturasRectificadasElement);
        }


        if ($this->tipoFactura === self::TIPO_FACTURA_SUSTITUIDA && $this->facturasSustituidas !== null) {
            $facturasSustituidasElement = $this->createElement($doc, 'FacturasSustituidas');

            foreach ($this->facturasSustituidas as $facturaSustituidas) {
                $idFacturaSustituidasElement = $this->createElement($doc, 'IDFacturaSustituida');

                // Add IDEmisorFactura
                $idFacturaSustituidasElement->appendChild($this->createElement($doc, 'IDEmisorFactura', $facturaSustituidas['IDEmisorFactura']));

                // Add NumSerieFactura
                $idFacturaSustituidasElement->appendChild($this->createElement($doc, 'NumSerieFactura', $facturaSustituidas['NumSerieFactura']));

                // Add FechaExpedicionFactura
                $idFacturaSustituidasElement->appendChild($this->createElement($doc, 'FechaExpedicionFactura', $facturaSustituidas['FechaExpedicionFactura']));

                $facturasSustituidasElement->appendChild($idFacturaSustituidasElement);
            }

            $root->appendChild($facturasSustituidasElement);
        }



        // 7. ImporteRectificacion (only for R1 invoices with proper structure)
        if (in_array($this->tipoFactura, [self::TIPO_FACTURA_RECTIFICATIVA_PARTIAL, self::TIPO_FACTURA_RECTIFICATIVA, self::TIPO_FACTURA_SUSTITUIDA]) && $this->importeRectificacion !== null) {
            $importeRectificacionElement = $this->createElement($doc, 'ImporteRectificacion');

            // Add BaseRectificada
            $importeRectificacionElement->appendChild($this->createElement($doc, 'BaseRectificada', number_format($this->importeRectificacion['BaseRectificada'] ?? 0, 2, '.', '')));

            // Add CuotaRectificada
            $importeRectificacionElement->appendChild($this->createElement($doc, 'CuotaRectificada', number_format($this->importeRectificacion['CuotaRectificada'] ?? 0, 2, '.', '')));

            // Add CuotaRecargoRectificado (always present for R1)
            $importeRectificacionElement->appendChild($this->createElement($doc, 'CuotaRecargoRectificado', number_format($this->importeRectificacion['CuotaRecargoRectificado'] ?? 0, 2, '.', '')));

            $root->appendChild($importeRectificacionElement);
        }

        // 8. DescripcionOperacion
        $root->appendChild($this->createElement($doc, 'DescripcionOperacion', $this->descripcionOperacion));

        // 9. Destinatarios (if set)
        if ($this->destinatarios !== null && count($this->destinatarios) > 0) {
            $destinatariosElement = $this->createElement($doc, 'Destinatarios');
            foreach ($this->destinatarios as $destinatario) {
                $idDestinatarioElement = $this->createElement($doc, 'IDDestinatario');

                // Add NombreRazon
                $idDestinatarioElement->appendChild($this->createElement($doc, 'NombreRazon', $destinatario->getNombreRazon()));

                if ($destinatario instanceof PersonaFisicaJuridica) {
                    $idDestinatarioElement->appendChild($this->createElement($doc, 'NIF', $destinatario->getNif()));
                } elseif ($destinatario instanceof IDOtro) {
                    // Use the full IDOtro XML structure
                    $idDestinatarioElement->appendChild($destinatario->toXml($doc));
                }

                $destinatariosElement->appendChild($idDestinatarioElement);
            }
            $root->appendChild($destinatariosElement);
        }


        // 10. Desglose
        if ($this->desglose !== null) {
            $root->appendChild($this->desglose->toXml($doc));
        }

        // 11. CuotaTotal
        $root->appendChild($this->createElement($doc, 'CuotaTotal', (string)$this->cuotaTotal));

        // 12. ImporteTotal
        $root->appendChild($this->createElement($doc, 'ImporteTotal', (string)$this->importeTotal));

        // 13. Encadenamiento (always present for R1 invoices)
        // if ($this->encadenamiento !== null) {
        $root->appendChild($this->encadenamiento->toXml($doc));
        // } else {
        //     // Create default Encadenamiento if not set
        //     $encadenamientoElement = $this->createElement($doc, 'Encadenamiento');
        //     $encadenamientoElement->appendChild($this->createElement($doc, 'PrimerRegistro', 'S'));
        //     $root->appendChild($encadenamientoElement);
        // }

        // 14. SistemaInformatico (always present for R1 invoices)
        $root->appendChild($this->sistemaInformatico->toXml($doc));

        // 15. FechaHoraHusoGenRegistro
        $root->appendChild($this->createElement($doc, 'FechaHoraHusoGenRegistro', $this->fechaHoraHusoGenRegistro));

        // 16. TipoHuella
        $root->appendChild($this->createElement($doc, 'TipoHuella', $this->tipoHuella));

        // 17. Huella
        $root->appendChild($this->createElement($doc, 'Huella', $this->huella));

        return $root;
    }

    public function toXmlString(): string
    {
        // Validate the invoice configuration first
        $this->validate();

        // Enable user error handling for XML operations
        $previousErrorSetting = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $doc = new \DOMDocument('1.0', 'UTF-8');
            $doc->preserveWhiteSpace = false;
            $doc->formatOutput = true;

            // Create root element using toXml method
            $root = $this->toXml($doc);
            $doc->appendChild($root);

            // Sign the document if certificates are set
            if ($this->privateKeyPath && $this->certificatePath) {
                $this->signXml($doc);
            }

            $xml = $doc->saveXML();
            if ($xml === false) {
                throw new \DOMException('Failed to generate XML');
            }

            return $xml;
        } catch (\ErrorException $e) {
            // Convert any libxml errors to DOMException
            $errors = libxml_get_errors();
            libxml_clear_errors();
            if (!empty($errors)) {
                throw new \DOMException($errors[0]->message);
            }
            throw new \DOMException($e->getMessage());
        } finally {
            // Restore previous error handling setting
            libxml_use_internal_errors($previousErrorSetting);
            libxml_clear_errors();

        }

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

        // Add ObligadoEmision content
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

    protected function validateXml(\DOMDocument $doc): void
    {
        $xsdPath = $this->getXsdPath();
        if (!file_exists($xsdPath)) {
            throw new \DOMException("Schema file not found at: $xsdPath");
        }

        // Enable user error handling
        $previousErrorSetting = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            if (!@$doc->schemaValidate($xsdPath)) {
                $errors = libxml_get_errors();
                if (!empty($errors)) {
                    throw new \DOMException($errors[0]->message);
                }
                throw new \DOMException('XML does not validate against schema');
            }
        } catch (\ErrorException $e) {
            // Convert ErrorException to DOMException
            throw new \DOMException($e->getMessage());
        } finally {
            // Restore previous error handling setting and clear any remaining errors
            libxml_use_internal_errors($previousErrorSetting);
            libxml_clear_errors();
        }
    }

    protected function getXsdPath(): string
    {
        return __DIR__ . '/../xsd/SuministroInformacion.xsd';
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

    public static function fromDOMElement(\DOMElement $element): self
    {
        $invoice = new self();

        // Parse IDVersion
        $idVersionElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'IDVersion')->item(0);
        if ($idVersionElement) {
            $invoice->setIDVersion($idVersionElement->nodeValue);
        }

        // Parse IDFactura
        $idFacturaElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'IDFactura')->item(0);
        if ($idFacturaElement) {
            $invoice->setIdFactura(IDFactura::fromDOMElement($idFacturaElement));
        }

        // Parse RefExterna
        $refExternaElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'RefExterna')->item(0);
        if ($refExternaElement) {
            $invoice->setRefExterna($refExternaElement->nodeValue);
        }

        // Parse NombreRazonEmisor
        $nombreRazonEmisorElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'NombreRazonEmisor')->item(0);
        if ($nombreRazonEmisorElement) {
            $invoice->setNombreRazonEmisor($nombreRazonEmisorElement->nodeValue);
        }

        // Parse Subsanacion
        $subsanacion = self::getElementText($element, 'Subsanacion');
        if ($subsanacion !== null) {
            $invoice->setSubsanacion($subsanacion);
        }

        // Parse RechazoPrevio
        $rechazoPrevio = self::getElementText($element, 'RechazoPrevio');
        if ($rechazoPrevio !== null) {
            $invoice->setRechazoPrevio($rechazoPrevio);
        }

        // Parse EmitidaPorTerceroODestinatario
        $emitidaPorTerceroElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'EmitidaPorTerceroODestinatario')->item(0);
        if ($emitidaPorTerceroElement) {
            $invoice->setEmitidaPorTerceroODestinatario($emitidaPorTerceroElement->nodeValue);
        }

        // Parse TipoFactura
        $tipoFacturaElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'TipoFactura')->item(0);
        if ($tipoFacturaElement) {
            $invoice->setTipoFactura($tipoFacturaElement->nodeValue);
        }

        // Parse FechaOperacion
        $fechaOperacionElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'FechaOperacion')->item(0);
        if ($fechaOperacionElement) {
            $invoice->setFechaOperacion($fechaOperacionElement->nodeValue);
        }

        // Parse TipoRectificativa
        $tipoRectificativaElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'TipoRectificativa')->item(0);
        if ($tipoRectificativaElement) {
            $invoice->setTipoRectificativa($tipoRectificativaElement->nodeValue);
        }

        // Parse DescripcionOperacion
        $descripcionOperacionElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'DescripcionOperacion')->item(0);
        if ($descripcionOperacionElement) {
            $invoice->setDescripcionOperacion($descripcionOperacionElement->nodeValue);
        }

        $cupon = self::getElementText($element, 'Cupon');
        if ($cupon !== null) {
            $invoice->setCupon($cupon);
        }

        $facturaSimplificadaArt7273 = self::getElementText($element, 'FacturaSimplificadaArt7273');
        if ($facturaSimplificadaArt7273 !== null) {
            $invoice->setFacturaSimplificadaArt7273($facturaSimplificadaArt7273);
        }

        // Parse FacturaSinIdentifDestinatarioArt61d
        $facturaSinIdentifElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'FacturaSinIdentifDestinatarioArt61d')->item(0);
        if ($facturaSinIdentifElement) {
            $invoice->setFacturaSinIdentifDestinatarioArt61d($facturaSinIdentifElement->nodeValue);
        }

        // Parse Macrodato
        $macrodatoElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'Macrodato')->item(0);
        if ($macrodatoElement) {
            $invoice->setMacrodato($macrodatoElement->nodeValue);
        }

        // Parse Tercero
        $terceroElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'Tercero')->item(0);
        if ($terceroElement) {
            $tercero = new PersonaFisicaJuridica();

            // Get NombreRazon
            $nombreRazonElement = $terceroElement->getElementsByTagNameNS(self::XML_NAMESPACE, 'NombreRazon')->item(0);
            if ($nombreRazonElement) {
                $tercero->setRazonSocial($nombreRazonElement->nodeValue);
            }

            // Get NIF
            $nifElement = $terceroElement->getElementsByTagNameNS(self::XML_NAMESPACE, 'NIF')->item(0);
            if ($nifElement) {
                $tercero->setNif($nifElement->nodeValue);
            }

            $invoice->setTercero($tercero);
        }

        // Parse Desglose
        $desgloseElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'Desglose')->item(0);
        if ($desgloseElement) {
            $invoice->setDesglose(Desglose::fromDOMElement($desgloseElement));
        }

        // Parse CuotaTotal
        $cuotaTotalElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'CuotaTotal')->item(0);
        if ($cuotaTotalElement) {
            $invoice->setCuotaTotal((float)$cuotaTotalElement->nodeValue);
        }

        // Parse ImporteTotal
        $importeTotalElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'ImporteTotal')->item(0);
        if ($importeTotalElement) {
            $invoice->setImporteTotal((float)$importeTotalElement->nodeValue);
        }

        // Parse Encadenamiento
        $encadenamientoElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'Encadenamiento')->item(0);
        if ($encadenamientoElement) {
            $invoice->setEncadenamiento(Encadenamiento::fromDOMElement($encadenamientoElement));
        }

        // Parse SistemaInformatico
        $sistemaInformaticoElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'SistemaInformatico')->item(0);
        if ($sistemaInformaticoElement) {
            $invoice->setSistemaInformatico(SistemaInformatico::fromDOMElement($sistemaInformaticoElement));
        }

        // Parse FechaHoraHusoGenRegistro
        $fechaHoraElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'FechaHoraHusoGenRegistro')->item(0);
        if ($fechaHoraElement) {
            $invoice->setFechaHoraHusoGenRegistro($fechaHoraElement->nodeValue);
        }

        // Parse NumRegistroAcuerdoFacturacion
        $numRegistroElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'NumRegistroAcuerdoFacturacion')->item(0);
        if ($numRegistroElement) {
            $invoice->setNumRegistroAcuerdoFacturacion($numRegistroElement->nodeValue);
        }

        // Parse IdAcuerdoSistemaInformatico
        $idAcuerdoElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'IdAcuerdoSistemaInformatico')->item(0);
        if ($idAcuerdoElement) {
            $invoice->setIdAcuerdoSistemaInformatico($idAcuerdoElement->nodeValue);
        }

        // Parse TipoHuella
        $tipoHuellaElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'TipoHuella')->item(0);
        if ($tipoHuellaElement) {
            $invoice->setTipoHuella($tipoHuellaElement->nodeValue);
        }

        // Parse Huella
        $huellaElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'Huella')->item(0);
        if ($huellaElement) {
            $invoice->setHuella($huellaElement->nodeValue);
        }

        // Parse Destinatarios


        // Parse Destinatarios
        $destinatariosElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'Destinatarios')->item(0);
        if ($destinatariosElement) {
            $destinatarios = [];
            $idDestinatarioElements = $destinatariosElement->getElementsByTagNameNS(self::XML_NAMESPACE, 'IDDestinatario');
            foreach ($idDestinatarioElements as $idDestinatarioElement) {
                // Check if it's an IDOtro type first
                $idOtroElement = $idDestinatarioElement->getElementsByTagNameNS(self::XML_NAMESPACE, 'IDOtro')->item(0);
                if ($idOtroElement) {
                    // Create IDOtro object - it doesn't store NombreRazon
                    $destinatario = IDOtro::fromDOMElement($idOtroElement);
                } else {
                    // Create PersonaFisicaJuridica object
                    $destinatario = new PersonaFisicaJuridica();

                    // Get NIF
                    $nifElement = $idDestinatarioElement->getElementsByTagNameNS(self::XML_NAMESPACE, 'NIF')->item(0);
                    if ($nifElement) {
                        $destinatario->setNif($nifElement->nodeValue);
                    }
                }

                // Get NombreRazon from the parent element for both types
                $nombreRazonElement = $idDestinatarioElement->getElementsByTagNameNS(self::XML_NAMESPACE, 'NombreRazon')->item(0);
                if ($nombreRazonElement) {
                    if ($destinatario instanceof PersonaFisicaJuridica) {
                        $destinatario->setNombreRazon($nombreRazonElement->nodeValue);
                    }
                    // For IDOtro, we don't set NombreRazon since it doesn't have that property
                }

                $destinatarios[] = $destinatario;
            }
            $invoice->setDestinatarios($destinatarios);
        }



        return $invoice;
    }

    protected static function getElementText(\DOMElement $element, string $tagName): ?string
    {
        $node = $element->getElementsByTagNameNS(self::XML_NAMESPACE, $tagName)->item(0);
        return $node ? $node->nodeValue : null;
    }

    /**
     * Create a cancellation record for this invoice
     */
    public function createCancellation(): RegistroAnulacion
    {
        $cancellation = new RegistroAnulacion();
        $cancellation
            ->setSistemaInformatico($this->getSistemaInformatico())
            ->setNombreRazonEmisor($this->getNombreRazonEmisor())
            ->setIdEmisorFactura($this->getIdFactura()->getIdEmisorFactura())
            ->setNumSerieFactura($this->getIdFactura()->getNumSerieFactura())
            ->setFechaExpedicionFactura($this->getIdFactura()->getFechaExpedicionFactura())
            ->setEncadenamiento($this->getEncadenamiento())
            ->setMotivoAnulacion('1'); // Sustitución por otra factura

        return $cancellation;
    }

    public function serialize()
    {
        return serialize($this);
    }

    public static function unserialize(string $data): self
    {
        $object = unserialize($data);

        if (!$object instanceof self) {
            throw new \InvalidArgumentException('Invalid serialized data - not an Invoice object');
        }

        return $object;
    }
}
