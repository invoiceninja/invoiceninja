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

class MailgunInboundWebhookTransformer
{
    public function transform($data)
    {
        $ingresEmail = new IngresEmail();

        $ingresEmail->from = $data["sender"];
        $ingresEmail->subject = $data["subject"];
        $ingresEmail->plain_message = $data["body-plain"];
        $ingresEmail->html_message = $data["body-html"];
        $ingresEmail->date = now(); // TODO

        // parse documents as UploadedFile from webhook-data
        foreach ($data["Attachments"] as $attachment) {
            $ingresEmail->documents[] = TempFile::UploadedFileFromRaw($attachment["Content"], $attachment["Name"], $attachment["ContentType"]);
        }

        return $ingresEmail;
    }
}
