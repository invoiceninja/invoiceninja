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

namespace App\Helpers\IngresMail\Transformer;

use App\Services\IngresEmail\IngresEmail;
use App\Utils\TempFile;
use Illuminate\Support\Carbon;

class MailgunInboundWebhookTransformer
{
    public function transform($data)
    {
        $ingresEmail = new IngresEmail();

        $ingresEmail->from = $data["From"];
        $ingresEmail->to = $data["To"];
        $ingresEmail->subject = $data["Subject"];
        $ingresEmail->plain_message = $data["body-plain"];
        $ingresEmail->html_message = $data["body-html"];
        $ingresEmail->date = Carbon::createFromTimestamp((int) $data["timestamp"]);

        // parse documents as UploadedFile from webhook-data
        foreach (json_decode($data["attachments"]) as $attachment) {

            // prepare url with credentials before downloading :: https://github.com/mailgun/mailgun.js/issues/24
            $url = $attachment->url;
            $credentials = config('services.mailgun.domain') . ":" . config('services.mailgun.secret') . "@";
            $url = str_replace("http://", "http://" . $credentials, $url);
            $url = str_replace("https://", "https://" . $credentials, $url);

            // download file and save to tmp dir
            $ingresEmail->documents[] = TempFile::UploadedFileFromUrl($url, $attachment->name, $attachment->{"content-type"});

        }

        return $ingresEmail;
    }
}
