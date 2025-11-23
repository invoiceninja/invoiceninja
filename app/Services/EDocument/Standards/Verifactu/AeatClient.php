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

class AeatClient
{
    private string $base_url = 'https://www1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';

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

    public function send($xml): array
    {

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
            ->withBody($xml, 'text/xml')
            ->post($this->base_url);

        $success = $response->successful();

        $responseProcessor = new ResponseProcessor();

        $parsedResponse = $responseProcessor->processResponse($response->body());

        nlog($parsedResponse);

        return $parsedResponse;

    }
}
