<?php

namespace App\Utils\Gotenberg;

use Illuminate\Support\Facades\Http;

class GotenbergPdf
{
    /**
     * Convert HTML to PDF using Gotenberg service
     *
     * @param string $html
     * @return string PDF content
     * @throws \Exception
     */
    public function convertHtmlToPdf(string $html): string
    {
        $url = config('ninja.gotenberg_url') ?: env('GOTENBERG_API_URL', 'http://localhost:3000');
        
        try {
            $response = Http::timeout(60)
                ->asMultipart()
                ->attach('files', $html, 'index.html')
                ->post("{$url}/forms/chromium/convert/html", [
                    'marginTop' => '0',
                    'marginBottom' => '0',
                    'marginLeft' => '0',
                    'marginRight' => '0',
                    'preferCssPageSize' => 'true',
                ]);

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
