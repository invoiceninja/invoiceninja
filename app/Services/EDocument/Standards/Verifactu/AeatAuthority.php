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

namespace App\Services\EDocument\Standards\Verifactu;

use App\Services\EDocument\Standards\Verifactu\ResponseProcessor;
use Illuminate\Support\Facades\Http;

class AeatAuthority
{
    // @todo - in the UI, the user must navigate to AEAT link, and add Invoice Ninja as a third party. We cannot send without this.
    // @todo - need to store the verification of this in the company
    // https://sede.agenciatributaria.gob.es/Sede/ayuda/consultas-informaticas/otros-servicios-ayuda-tecnica/consultar-confirmar-renunciar-apoderamiento-recibido.html
    // @todo - register with AEAT as a third party - power of attorney
    // Log in with their certificate, DNIe, or Cl@ve PIN.
    // Select: "Otorgar poder a un tercero"
    // Enter:
    // Your SaaS company's NIF as the authorized party
    // Power code: LGTINVDI (or GENERALDATPE)
    // Confirm
    // https://sede.agenciatributaria.gob.es/wlpl/BDC/conapoderWS

    /*
    * Production URL works, sandbox URL does not!
    */

    private string $base_url = 'https://sede.agenciatributaria.gob.es/wlpl/BDC/conapoderWS';

    private string $sandbox_url = 'https://prewww1.aeat.es/wlpl/BDC/conapoderWS';

    public function __construct()
    {

    }

    public function setTestMode(): self
    {
        $this->base_url = $this->sandbox_url;

        return $this;
    }

    public function run(string $client_nif): array
    {

        $sender_nif = config('services.verifactu.sender_nif');
        $certificate = config('services.verifactu.certificate');
        $ssl_key = config('services.verifactu.ssl_key');

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:apod="http://www2.agenciatributaria.gob.es/apoderamiento/ws/apoderamientos">
    <soapenv:Header/>
    <soapenv:Body>
        <apod:ConsultaApoderamiento>
            <apod:identificadorApoderado>
                <apod:nifRepresentante>{$sender_nif}</apod:nifRepresentante>
            </apod:identificadorApoderado>
            <apod:identificadorPoderdante>
                <apod:nifPoderdante>{$client_nif}</apod:nifPoderdante>
            </apod:identificadorPoderdante>
            <apod:codigoPoder>LGTINVDI</apod:codigoPoder>
        </apod:ConsultaApoderamiento>
    </soapenv:Body>
</soapenv:Envelope>
XML;

        $signingService = new \App\Services\EDocument\Standards\Verifactu\Signing\SigningService($xml, file_get_contents($ssl_key), file_get_contents($certificate));
        $soapXml = $signingService->sign();

        $response = Http::withHeaders([
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => '',
                ])
                ->withOptions([
                    'cert' => $certificate,
                    'ssl_key' => $ssl_key,
                    'verify' => false,
                    'timeout' => 30,
                ])
                ->withBody($soapXml, 'text/xml')
                ->post($this->base_url);

        $success = $response->successful();

        nlog($soapXml);
        $responseProcessor = new ResponseProcessor();

        $parsedResponse = $responseProcessor->processResponse($response->body());
        nlog($response->body());
        nlog($parsedResponse);

        return $parsedResponse;

    }
}
