<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\HostedPDF;

use GuzzleHttp\RequestOptions;

class NinjaPdf
{
    private $url = '';

    public function build($html)
    {
        $url = config('ninja.pdf_url') ?: '';
        
        if (empty($url)) {
            throw new \Exception('Hosted PDF generation is enabled but PDF_URL is not configured. Please set PDF_URL in your .env file or disable hosted PDF generation.');
        }

        $client = new \GuzzleHttp\Client(['headers' => [
            'X-Ninja-Token' => 'test_token_for_now',
            'X-URL' => config('ninja.app_url'),
            ],
        ]);

        $response = $client->post($url, [
            RequestOptions::JSON => ['html' => $html],
        ]);


        return $response->getBody()->getContents();
    }

}
