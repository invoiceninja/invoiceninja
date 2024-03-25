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

namespace App\Helpers\InboundMail\Transformer;

use App\Services\InboundMail\InboundMail;
use App\Utils\TempFile;
use Ddeboer\Imap\MessageInterface;

class ImapMailTransformer
{

    public function transform(MessageInterface $mail)
    {
        $inboundMail = new InboundMail();

        $inboundMail->from = $mail->getSender();
        $inboundMail->subject = $mail->getSubject();
        $inboundMail->plain_message = $mail->getBodyText();
        $inboundMail->html_message = $mail->getBodyHtml();
        $inboundMail->date = $mail->getDate();

        // parse documents as UploadedFile
        foreach ($mail->getAttachments() as $attachment) {
            $inboundMail->documents[] = TempFile::UploadedFileFromRaw($attachment->getContent(), $attachment->getFilename(), $attachment->getEncoding());
        }

        return $inboundMail;
    }
}
