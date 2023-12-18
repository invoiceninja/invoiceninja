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
use Ddeboer\Imap\MessageInterface;

class ImapMailTransformer
{

    public function transform(MessageInterface $mail)
    {
        $ingresEmail = new IngresEmail();

        $ingresEmail->from = $mail->getSender();
        $ingresEmail->subject = $mail->getSubject();
        $ingresEmail->plain_message = $mail->getBodyText();
        $ingresEmail->html_message = $mail->getBodyHtml();
        $ingresEmail->date = $mail->getDate();

        // parse documents as UploadedFile
        foreach ($mail->getAttachments() as $attachment) {
            $ingresEmail->documents[] = TempFile::UploadedFileFromRaw($attachment->getContent(), $attachment->getFilename(), $attachment->getEncoding());
        }

        return $ingresEmail;
    }
}
