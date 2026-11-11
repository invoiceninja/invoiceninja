<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */
namespace App\Utils\Gotenberg;

use Illuminate\Support\Facades\Http;

class GotenbergPdf
{
    public const PDF_A_3B = 'PDF/A-3b';

    /**
     * Convert HTML to PDF using Gotenberg service
     *
     * @param string $html
     * @param string|null $pdfa
     * @return string PDF content
     * @throws \Exception
     */
    public function convertHtmlToPdf(string $html, ?string $pdfa = null): string
    {
        $url = config('ninja.gotenberg_url') ?: env('GOTENBERG_API_URL', 'http://localhost:3000');

        $options = [
            'marginTop' => '0',
            'marginBottom' => '0',
            'marginLeft' => '0',
            'marginRight' => '0',
            'preferCssPageSize' => 'true',
        ];

        if ($pdfa) {
            $options['pdfa'] = $pdfa;
        }

        try {
            $response = Http::timeout(60)
                ->asMultipart()
                ->attach('files', $html, 'index.html')
                ->post("{$url}/forms/chromium/convert/html", $options);

            if ($response->successful()) {
                return $response->body();
            }

            throw new \Exception("Gotenberg PDF generation failed: " . $response->status() . " - " . $response->body());

        } catch (\Exception $e) {
            nlog("Gotenberg Error: " . $e->getMessage());
            throw new \Exception("Failed to generate PDF via Gotenberg: " . $e->getMessage());
        }
    }
}
