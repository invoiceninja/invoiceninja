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

class PostmarkInboundWebhookTransformer
{
    public function process($data)
    {

        $ingresEmail = new IngresEmail();

        $ingresEmail->from = $data["From"];
        $ingresEmail->subject = $data["Subject"];
        $ingresEmail->plain_message = $data["TextBody"];
        $ingresEmail->html_message = $data["HtmlBody"];
        $ingresEmail->date = $data["Date"]; // TODO: parsing

        // parse documents as UploadedFile from webhook-data
        foreach ($data["Attachments"] as $attachment) {
            $ingresEmail->documents[] = TempFile::UploadedFileFromRaw($attachment["Content"], $attachment["Name"], $attachment["ContentType"]);
        }

        return $ingresEmail;

    }
    // {
    //   "FromName": "Postmarkapp Support",
    //   "MessageStream": "inbound",
    //   "From": "support@postmarkapp.com",
    //   "FromFull": {
    //     "Email": "support@postmarkapp.com",
    //     "Name": "Postmarkapp Support",
    //     "MailboxHash": ""
    //   },
    //   "To": "\"Firstname Lastname\" <yourhash+SampleHash@inbound.postmarkapp.com>",
    //   "ToFull": [
    //     {
    //       "Email": "yourhash+SampleHash@inbound.postmarkapp.com",
    //       "Name": "Firstname Lastname",
    //       "MailboxHash": "SampleHash"
    //     }
    //   ],
    //   "Cc": "\"First Cc\" <firstcc@postmarkapp.com>, secondCc@postmarkapp.com>",
    //   "CcFull": [
    //     {
    //       "Email": "firstcc@postmarkapp.com",
    //       "Name": "First Cc",
    //       "MailboxHash": ""
    //     },
    //     {
    //       "Email": "secondCc@postmarkapp.com",
    //       "Name": "",
    //       "MailboxHash": ""
    //     }
    //   ],
    //   "Bcc": "\"First Bcc\" <firstbcc@postmarkapp.com>, secondbcc@postmarkapp.com>",
    //   "BccFull": [
    //     {
    //       "Email": "firstbcc@postmarkapp.com",
    //       "Name": "First Bcc",
    //       "MailboxHash": ""
    //     },
    //     {
    //       "Email": "secondbcc@postmarkapp.com",
    //       "Name": "",
    //       "MailboxHash": ""
    //     }
    //   ],
    //   "OriginalRecipient": "yourhash+SampleHash@inbound.postmarkapp.com",
    //   "Subject": "Test subject",
    //   "MessageID": "73e6d360-66eb-11e1-8e72-a8904824019b",
    //   "ReplyTo": "replyto@postmarkapp.com",
    //   "MailboxHash": "SampleHash",
    //   "Date": "Fri, 1 Aug 2014 16:45:32 -04:00",
    //   "TextBody": "This is a test text body.",
    //   "HtmlBody": "<html><body><p>This is a test html body.<\/p><\/body><\/html>",
    //   "StrippedTextReply": "This is the reply text",
    //   "Tag": "TestTag",
    //   "Headers": [
    //     {
    //       "Name": "X-Header-Test",
    //       "Value": ""
    //     },
    //     {
    //       "Name": "X-Spam-Status",
    //       "Value": "No"
    //     },
    //     {
    //       "Name": "X-Spam-Score",
    //       "Value": "-0.1"
    //     },
    //     {
    //       "Name": "X-Spam-Tests",
    //       "Value": "DKIM_SIGNED,DKIM_VALID,DKIM_VALID_AU,SPF_PASS"
    //     }
    //   ],
    //   "Attachments": [
    //     {
    //       "Name": "test.txt",
    //       "Content": "VGhpcyBpcyBhdHRhY2htZW50IGNvbnRlbnRzLCBiYXNlLTY0IGVuY29kZWQu",
    //       "ContentType": "text/plain",
    //       "ContentLength": 45
    //     }
    //   ]
    // }
}
