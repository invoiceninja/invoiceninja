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

use Illuminate\Support\Facades\Http;
use App\Services\EDocument\Standards\Verifactu\ResponseProcessor;
use App\Services\EDocument\Standards\Verifactu\Signing\SigningService;

class AeatClient
{
    private string $base_url = 'https://www1.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';

    private string $sandbox_url = 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';

    public string $base_qr_url = 'https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR?csv=%s&nif=%s&numserie=%s&fecha=%s&importe=%s';

    private string $sandbox_qr_url = 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?csv=%s&nif=%s&numserie=%s&fecha=%s&importe=%s';


    public function __construct(private ?string $certificate = null, private ?string $ssl_key = null)
    {
        $this->init();
    }

    /**
     * initialize the certificates
     *
     * @return self
     */
    private function init(): self
    {
        $this->certificate = $this->certificate ?? config('services.verifactu.certificate');
        $this->ssl_key = $this->ssl_key ?? config('services.verifactu.ssl_key');

        if (config('services.verifactu.test_mode')) {
            $this->setTestMode();
        }

        return $this;
    }

    /**
     * setTestMode
     *
     * @return self
     */
    public function setTestMode(): self
    {
        $this->base_url = $this->sandbox_url;
        $this->base_qr_url = $this->sandbox_qr_url;

        return $this;
    }

    /**
     * Sign SOAP envelope with XML Digital Signature
     *
     * @param string $xml - Unsigned SOAP envelope
     * @return string - Signed SOAP envelope
     */
    private function signSoapEnvelope(string $xml): string
    {
        try {
            $signingService = new SigningService(
                $xml,
                file_get_contents($this->ssl_key),
                file_get_contents($this->certificate)
            );
            return $signingService->sign();
        } catch (\Exception $e) {
            nlog("Error signing SOAP envelope: " . $e->getMessage());
            throw $e;
        }
    }

    public function send($xml): array
    {
        // Sign the SOAP envelope before sending
        $signed_xml = $this->signSoapEnvelope($xml);

        nlog("AEAT Request URL: " . $this->base_url);
        nlog("Signed SOAP envelope size: " . strlen($signed_xml) . " bytes");

        $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => '',
            ])
            ->withOptions([
                'cert' => $this->certificate,
                'ssl_key' => $this->ssl_key,
                'verify' => false,
                'timeout' => 30,
            ])
            ->withBody($signed_xml, 'text/xml')
            ->post($this->base_url);

        $success = $response->successful();

        nlog("AEAT Response HTTP Code: " . $response->status());

        $responseProcessor = new ResponseProcessor();

        $parsedResponse = $responseProcessor->processResponse($response->body());

        nlog($parsedResponse);

        return $parsedResponse;

    }
}
