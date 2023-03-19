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

    public function __construct(protected string $vat_number, protected string $country_code)
    {
    }

    public function run()
    {
        return $this->checkvat_number();
    }

    private function checkvat_number(): array
    {
        $wsdl = "https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl";
        try {
            $client = new \SoapClient($wsdl);
            $params = [
                'countryCode' => $this->country_code,
                'vatNumber' => $this->vat_number
            ];
            $response = $client->checkVat($params);

            if ($response->valid) {
                return [
                    'valid' => true,
                    'name' => $response->name,
                    'address' => $response->address
                ];
            } else {
                return ['valid' => false];
            }
        } catch (\SoapFault $e) {
            // Handle error, e.g., log or display an error message
            return ['error' => $e->getMessage()];
        }
    }

}
