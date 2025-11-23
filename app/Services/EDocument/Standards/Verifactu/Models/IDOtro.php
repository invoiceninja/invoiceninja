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

class IDOtro extends BaseXmlModel
{
    private const VALID_ID_TYPES = [
        '01', // NIF IVA (EU operator with VAT number, non-Spanish)
        '02', // NIF in Spain
        '03', // VAT number (EU operator without Spanish NIF)
        '04', // Passport
        '05', // Official ID document
        '06', // Residence certificate
        '07', // Person without identification code
        '08', // Other supporting document
        '09', // Tax ID from third country
    ];

    private ?string $nombreRazon = '';

    /**
     * __construct
     *
     * @param  string $codigoPais ISO 3166-1 alpha-2 country code (e.g., ES, FR, US)
     * @param  string $idType AEAT ID type code (e.g., '07' = Person without identification code)
     * @param  string $id Identifier value, e.g., passport number, tax ID, or placeholder
     * @return void
     */
    public function __construct(private string $codigoPais = 'ES', private string $idType = '06', private string $id = 'NO_DISPONIBLE')
    {

    }

    public function getNombreRazon(): string
    {
        return $this->nombreRazon;
    }

    public function getCodigoPais(): string
    {
        return $this->codigoPais;
    }

    public function getIdType(): string
    {
        return $this->idType;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setNombreRazon(string $nombreRazon): self
    {
        $this->nombreRazon = $nombreRazon;
        return $this;
    }

    public function setCodigoPais(string $codigoPais): self
    {
        $this->codigoPais = strtoupper($codigoPais);
        return $this;
    }

    public function setIdType(string $idType): self
    {
        $this->idType = $idType;
        return $this;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Returns the array structure for serialization to XML
     */
    public function toArray(): array
    {
        return [
            'CodigoPais' => $this->codigoPais,
            'IDType'     => $this->idType,
            'ID'         => $this->id,
        ];
    }

    /**
     * Returns the XML fragment for IDOtro
     */
    public function toXml(\DOMDocument $doc): \DOMElement
    {
        $root = $this->createElement($doc, 'IDOtro');

        $root->appendChild($this->createElement($doc, 'CodigoPais', $this->codigoPais));
        $root->appendChild($this->createElement($doc, 'IDType', $this->idType));
        $root->appendChild($this->createElement($doc, 'ID', $this->id));

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
        $idOtro = new self();

        $codigoPaisElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'CodigoPais')->item(0);
        if ($codigoPaisElement) {
            $idOtro->setCodigoPais($codigoPaisElement->nodeValue);
        }

        $idTypeElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'IDType')->item(0);
        if ($idTypeElement) {
            $idOtro->setIdType($idTypeElement->nodeValue);
        }

        $idElement = $element->getElementsByTagNameNS(self::XML_NAMESPACE, 'ID')->item(0);
        if ($idElement) {
            $idOtro->setId($idElement->nodeValue);
        }

        return $idOtro;
    }
}
