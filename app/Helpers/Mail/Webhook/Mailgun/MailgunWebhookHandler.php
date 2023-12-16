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

namespace App\Helpers\Mail\Webhook\Maigun;

use App\Helpers\Mail\Webhook\BaseWebhookHandler;
use App\Utils\TempFile;

class MailgunWebhookHandler extends BaseWebhookHandler
{
    public function process($data)
    {

        $from = $data["sender"];
        $subject = $data["subject"];
        $plain_message = $data["body-plain"];
        $html_message = $data["body-html"];
        $date = now(); // TODO

        // parse documents as UploadedFile from webhook-data
        $documents = [];
        foreach ($data["Attachments"] as $attachment) {
            $documents[] = TempFile::UploadedFileFromRaw($attachment["Content"], $attachment["Name"], $attachment["ContentType"]);
        }

        return $this->createExpense(
            $from,
            $subject,
            $plain_message,
            $html_message,
            $date,
            $documents,
        );

    }
}
