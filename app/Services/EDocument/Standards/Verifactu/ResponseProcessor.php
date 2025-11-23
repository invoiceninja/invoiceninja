<?php

namespace App\Services\EDocument\Standards\Verifactu;

use DOMDocument;
use DOMElement;
use DOMNodeList;
use Exception;
use Illuminate\Support\Facades\Log;

class ResponseProcessor
{
    private DOMDocument $dom;
    private ?DOMElement $root = null;

    public function __construct()
    {
        $this->dom = new DOMDocument();
    }

    /**
     * Process AEAT XML response and return structured array
     */
    public function processResponse(string $xmlResponse): array
    {
        try {
            $this->loadXml($xmlResponse);

            nlog($this->dom->saveXML());

            return [
                'success' => $this->isSuccessful(),
                'status' => $this->getStatus(),
                'errors' => $this->getErrors(),
                'warnings' => $this->getWarnings(),
                'data' => $this->getResponseData(),
                'metadata' => $this->getMetadata(),
                'guid' => $this->getGuid(),
                'raw_response' => $xmlResponse
            ];
        } catch (Exception $e) {
            Log::error('Error processing AEAT response', [
                'error' => $e->getMessage(),
                'xml' => $xmlResponse
            ]);

            return [
                'success' => false,
                'error' => 'Failed to process response: ' . $e->getMessage(),
                'raw_response' => $xmlResponse
            ];
        }
    }

    /**
     * Load XML into DOM
     */
    private function loadXml(string $xml): void
    {
        libxml_use_internal_errors(true);
        libxml_clear_errors();

        if (!$this->dom->loadXML($xml)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new Exception('Invalid XML: ' . ($errors[0]->message ?? 'Unknown error'));
        }

        $this->root = $this->dom->documentElement;
    }

    private function getGuid(): ?string
    {
        return $this->getElementText('.//tikR:CSV') ?? null;
    }

    /**
     * Check if response indicates success
     */
    private function isSuccessful(): bool
    {
        $estadoEnvio = $this->getElementText('//tikR:EstadoEnvio');
        return $estadoEnvio === 'Correcto';
    }

    /**
     * Get response status
     */
    private function getStatus(): string
    {
        return $this->getElementText('//tikR:EstadoEnvio') ?? 'Unknown';
    }

    /**
     * Get all errors from response
     */
    private function getErrors(): array
    {
        $errors = [];

        // Check for SOAP faults
        $fault = $this->getElementText('//env:Fault/faultstring');
        if ($fault) {
            $errors[] = [
                'type' => 'SOAP_Fault',
                'code' => $this->getElementText('//env:Fault/faultcode'),
                'message' => $fault,
                'details' => $this->getElementText('//env:Fault/detail/callstack')
            ];
        }

        // Check for business logic errors
        $respuestaLineas = $this->dom->getElementsByTagNameNS(
            'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaSuministro.xsd',
            'RespuestaLinea'
        );

        foreach ($respuestaLineas as $linea) {
            $estadoRegistro = $this->getElementText('.//tikR:EstadoRegistro', $linea);

            if ($estadoRegistro === 'Incorrecto') {
                $errors[] = [
                    'type' => 'Business_Error',
                    'code' => $this->getElementText('.//tikR:CodigoErrorRegistro', $linea),
                    'message' => $this->getElementText('.//tikR:DescripcionErrorRegistro', $linea),
                    'invoice_data' => $this->getInvoiceData($linea)
                ];
            }
        }

        return $errors;
    }

    /**
     * Get warnings from response
     */
    private function getWarnings(): array
    {
        $warnings = [];

        // Check for subsanacion (correction) messages
        $subsanacion = $this->getElementText('//tikR:RespuestaLinea/tikR:Subsanacion');
        if ($subsanacion) {
            $warnings[] = [
                'type' => 'Subsanacion',
                'message' => $subsanacion
            ];
        }

        return $warnings;
    }

    /**
     * Get response data
     */
    private function getResponseData(): array
    {
        $data = [];

        // Get header information
        $cabecera = $this->getElement('//tikR:Cabecera');
        if ($cabecera) {
            $data['header'] = [
                'obligado_emision' => [
                    'nombre_razon' => $this->getElementText('.//tik:NombreRazon', $cabecera),
                    'nif' => $this->getElementText('.//tik:NIF', $cabecera)
                ]
            ];
        }

        // Get processing information
        $data['processing'] = [
            'tiempo_espera_envio' => $this->getElementText('//tikR:TiempoEsperaEnvio'),
            'estado_envio' => $this->getElementText('//tikR:EstadoEnvio')
        ];

        // Get invoice responses
        $data['invoices'] = $this->getInvoiceResponses();

        return $data;
    }

    /**
     * Get metadata from response
     */
    private function getMetadata(): array
    {
        return [
            'request_id' => $this->getElementText('//tikR:RespuestaLinea/tikR:IDFactura/tik:IDEmisorFactura'),
            'invoice_series' => $this->getElementText('//tikR:RespuestaLinea/tikR:IDFactura/tik:NumSerieFactura'),
            'invoice_date' => $this->getElementText('//tikR:RespuestaLinea/tikR:IDFactura/tik:FechaExpedicionFactura'),
            'operation_type' => $this->getElementText('//tikR:RespuestaLinea/tikR:Operacion/tik:TipoOperacion'),
            'external_reference' => $this->getElementText('//tikR:RespuestaLinea/tikR:RefExterna')
        ];
    }

    /**
     * Get invoice responses
     */
    private function getInvoiceResponses(): array
    {
        $invoices = [];

        $respuestaLineas = $this->dom->getElementsByTagNameNS(
            'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaSuministro.xsd',
            'RespuestaLinea'
        );

        foreach ($respuestaLineas as $linea) {
            $invoices[] = [
                'id_emisor' => $this->getElementText('.//tikR:IDFactura/tik:IDEmisorFactura', $linea),
                'num_serie' => $this->getElementText('.//tikR:IDFactura/tik:NumSerieFactura', $linea),
                'fecha_expedicion' => $this->getElementText('.//tikR:IDFactura/tik:FechaExpedicionFactura', $linea),
                'tipo_operacion' => $this->getElementText('.//tikR:Operacion/tik:TipoOperacion', $linea),
                'ref_externa' => $this->getElementText('.//tikR:RefExterna', $linea),
                'estado_registro' => $this->getElementText('.//tikR:EstadoRegistro', $linea),
                'codigo_error' => $this->getElementText('.//tikR:CodigoErrorRegistro', $linea),
                'descripcion_error' => $this->getElementText('.//tikR:DescripcionErrorRegistro', $linea),
                'subsanacion' => $this->getElementText('.//tikR:Subsanacion', $linea)
            ];
        }

        return $invoices;
    }

    /**
     * Get invoice data from response line
     */
    private function getInvoiceData(DOMElement $linea): array
    {
        return [
            'id_emisor' => $this->getElementText('.//tikR:IDFactura/tik:IDEmisorFactura', $linea),
            'num_serie' => $this->getElementText('.//tikR:IDFactura/tik:NumSerieFactura', $linea),
            'fecha_expedicion' => $this->getElementText('.//tikR:IDFactura/tik:FechaExpedicionFactura', $linea),
            'tipo_operacion' => $this->getElementText('.//tikR:Operacion/tik:TipoOperacion', $linea),
            'ref_externa' => $this->getElementText('.//tikR:RefExterna', $linea)
        ];
    }

    /**
     * Get element text by XPath
     */
    private function getElementText(string $xpath, ?DOMElement $context = null): ?string
    {
        $xpathObj = new \DOMXPath($this->dom);

        // Register namespaces
        $xpathObj->registerNamespace('env', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xpathObj->registerNamespace('tikR', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaSuministro.xsd');
        $xpathObj->registerNamespace('tik', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd');

        $nodeList = $context ? $xpathObj->query($xpath, $context) : $xpathObj->query($xpath);

        if ($nodeList && $nodeList->length > 0) {
            return trim($nodeList->item(0)->nodeValue);
        }

        return null;
    }

    /**
     * Get element by XPath
     */
    private function getElement(string $xpath): ?DOMElement
    {
        $xpathObj = new \DOMXPath($this->dom);

        // Register namespaces
        $xpathObj->registerNamespace('env', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xpathObj->registerNamespace('tikR', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaSuministro.xsd');
        $xpathObj->registerNamespace('tik', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd');

        $nodeList = $xpathObj->query($xpath);

        if ($nodeList && $nodeList->length > 0) {
            $node = $nodeList->item(0);
            return $node instanceof DOMElement ? $node : null;
        }

        return null;
    }

    /**
     * Check if response has errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->getErrors());
    }

    /**
     * Get first error message
     */
    public function getFirstError(): ?string
    {
        $errors = $this->getErrors();
        return $errors[0]['message'] ?? null;
    }

    /**
     * Get error codes
     */
    public function getErrorCodes(): array
    {
        $codes = [];
        $errors = $this->getErrors();

        foreach ($errors as $error) {
            if (isset($error['code'])) {
                $codes[] = $error['code'];
            }
        }

        return $codes;
    }

    /**
     * Check if specific error code exists
     */
    public function hasErrorCode(string $code): bool
    {
        return in_array($code, $this->getErrorCodes());
    }

    /**
     * Get summary of response
     */
    public function getSummary(): array
    {
        return [
            'success' => $this->isSuccessful(),
            'status' => $this->getStatus(),
            'error_count' => count($this->getErrors()),
            'warning_count' => count($this->getWarnings()),
            'invoice_count' => count($this->getInvoiceResponses()),
            'first_error' => $this->getFirstError(),
            'error_codes' => $this->getErrorCodes()
        ];
    }
}
