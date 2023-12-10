<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Tax;

class VatNumberCheck
{
    private array $response = [];

    public function __construct(protected ?string $vat_number, protected string $country_code)
    {
    }

    public function run()
    {
        return $this->checkvat_number();
    }

    private function checkvat_number(): self
    {
        $wsdl = "https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl";

        try {
            $client = new \SoapClient($wsdl);
            $params = [
                'countryCode' => $this->country_code,
                'vatNumber' => $this->vat_number ?? ''
            ];
            $response = $client->checkVat($params);

            if ($response->valid) {

                $this->response = [
                    'valid' => true,
                    'name' => $response->name,
                    'address' => $response->address
                ];
            } else {
                $this->response = ['valid' => false];
            }
        } catch (\SoapFault $e) {

            $this->response = ['valid' => false, 'error' => $e->getMessage()];
        }

        return $this;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function isValid(): bool
    {
        return $this->response['valid'];
    }

    public function getName()
    {
        return isset($this->response['name']) ? $this->response['name'] : '';
    }

    public function getAddress()
    {
        return isset($this->response['address']) ? $this->response['address'] : '';
    }
}
