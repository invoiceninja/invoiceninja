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

namespace App\Utils\PhantomJS;

use App\Utils\CurlUtils;
use Illuminate\Support\Facades\Response;

class Phantom
{
    public function convertHtmlToPdf($html)
    {
        $key = config('ninja.phantomjs_key');
        $phantom_url = "https://phantomjscloud.com/api/browser/v2/{$key}/";
        $pdf = CurlUtils::post($phantom_url, json_encode([
            'content'            => $html,
            'renderType'     => 'pdf',
            'outputAsJson'   => false,
            'renderSettings' => [
                'emulateMedia' => 'print',
                'pdfOptions'   => [
                    'preferCSSPageSize' => true,
                    'printBackground'   => true,
                ],
            ],
        ]));

        $response = Response::make($pdf, 200);
        $response->header('Content-Type', 'application/pdf');

        return $response;
    }

}
