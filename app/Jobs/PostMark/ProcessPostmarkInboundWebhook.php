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

namespace App\Jobs\Postmark;

use App\Libraries\MultiDB;
use App\Services\IngresEmail\IngresEmail;
use App\Services\IngresEmail\IngresEmailEngine;
use App\Utils\TempFile;
use Illuminate\Support\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;

class ProcessPostmarkInboundWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    /**
     * Create a new job instance.
     * $input consists of 2 informations: recipient|messageId
     */
    public function __construct(private string $input)
    {
    }

    /**
     * Execute the job.
     *
     * Mail from Storage
     * array (
     *   'FromName' => 'Max Mustermann',
     *   'MessageStream' => 'inbound',
     *   'From' => 'max@mustermann.de',
     *   'FromFull' =>
     *   array (
     *     'Email' => 'max@mustermann.de',
     *     'Name' => 'Max Mustermann',
     *     'MailboxHash' => NULL,
     *   ),
     *   'To' => '370c69ad9e41d616fc914b3c60250224@inbound.postmarkapp.com',
     *   'ToFull' =>
     *   array (
     *     0 =>
     *     array (
     *       'Email' => '370c69ad9e41d616fc914b3c60250224@inbound.postmarkapp.com',
     *       'Name' => NULL,
     *       'MailboxHash' => NULL,
     *     ),
     *   ),
     *   'Cc' => NULL,
     *   'CcFull' =>
     *   array (
     *   ),
     *   'Bcc' => NULL,
     *   'BccFull' =>
     *   array (
     *   ),
     *   'OriginalRecipient' => '370c69ad9e41d616fc914b3c60250224@inbound.postmarkapp.com',
     *   'Subject' => 'Re: adaw',
     *   'MessageID' => 'd37fde00-b4cf-4b64-ac64-e9f6da523c25',
     *   'ReplyTo' => NULL,
     *   'MailboxHash' => NULL,
     *   'Date' => 'Sun, 24 Mar 2024 13:17:52 +0100',
     *   'TextBody' => 'wadwad
     *
     *   Am So., 24. März 2024 um 13:17 Uhr schrieb Max Mustermann <max@mustermann.de>:
     *
     *   > test
     *   >
     *
     *   --
     *   test.de - Max Mustermann <https://test.de/>kontakt@test.de
     *   <mailto:kontakt@test.de>',
     *   'HtmlBody' => '<div dir="ltr">wadwad</div><br><div class="gmail_quote"><div dir="ltr" class="gmail_attr">Am So., 24. März 2024 um 13:17 Uhr schrieb Max Mustermann &lt;<a href="mailto:max@mustermann.de">max@mustermann.de</a>&gt;:<br></div><blockquote class="gmail_quote" style="margin:0px 0px 0px 0.8ex;border-left:1px solid rgb(204,204,204);padding-left:1ex"><div dir="ltr">test</div>
     *   </blockquote></div>
     *
     *   <br>
     *   <font size="3"><a href="https://test.de/" target="_blank">test.de - Max Mustermann</a></font><div><a href="mailto:kontakt@test.de" style="font-size:medium" target="_blank">kontakt@test.de</a><br></div>',
     *   'StrippedTextReply' => 'wadwad
     *
     *   Am So., 24. März 2024 um 13:17 Uhr schrieb Max Mustermann <max@mustermann.de>:',
     *   'Tag' => NULL,
     *   'Headers' =>
     *   array (
     *     0 =>
     *     array (
     *       'Name' => 'Return-Path',
     *       'Value' => '<max@mustermann.de>',
     *     ),
     *     1 =>
     *     array (
     *       'Name' => 'Received',
     *       'Value' => 'by p-pm-inboundg02a-aws-euwest1a.inbound.postmarkapp.com (Postfix, from userid 996)	id 8ED1A453CA4; Sun, 24 Mar 2024 12:18:10 +0000 (UTC)',
     *     ),
     *     2 =>
     *     array (
     *       'Name' => 'X-Spam-Checker-Version',
     *       'Value' => 'SpamAssassin 3.4.0 (2014-02-07) on	p-pm-inboundg02a-aws-euwest1a',
     *     ),
     *     3 =>
     *     array (
     *       'Name' => 'X-Spam-Status',
     *       'Value' => 'No',
     *     ),
     *     4 =>
     *     array (
     *       'Name' => 'X-Spam-Score',
     *       'Value' => '-0.1',
     *     ),
     *     5 =>
     *     array (
     *       'Name' => 'X-Spam-Tests',
     *       'Value' => 'DKIM_SIGNED,DKIM_VALID,DKIM_VALID_AU,HTML_MESSAGE,	RCVD_IN_DNSWL_NONE,RCVD_IN_MSPIKE_H2,RCVD_IN_ZEN_BLOCKED_OPENDNS,	SPF_HELO_NONE,SPF_PASS,URIBL_DBL_BLOCKED_OPENDNS,URIBL_ZEN_BLOCKED_OPENDNS',
     *     ),
     *     6 =>
     *     array (
     *       'Name' => 'Received-SPF',
     *       'Value' => 'pass (test.de: Sender is authorized to use \'max@mustermann.de\' in \'mfrom\' identity (mechanism \'include:_spf.google.com\' matched)) receiver=p-pm-inboundg02a-aws-euwest1a; identity=mailfrom; envelope-from="max@mustermann.de"; helo=mail-lf1-f51.google.com; client-ip=209.85.167.51',
     *     ),
     *     7 =>
     *     array (
     *       'Name' => 'Received',
     *       'Value' => 'from mail-lf1-f51.google.com (mail-lf1-f51.google.com [209.85.167.51])	(using TLSv1.2 with cipher ECDHE-RSA-AES128-GCM-SHA256 (128/128 bits))	(No client certificate requested)	by p-pm-inboundg02a-aws-euwest1a.inbound.postmarkapp.com (Postfix) with ESMTPS id 437BD453CA2	for <370c69ad9e41d616fc914b3c60250224@inbound.postmarkapp.com>; Sun, 24 Mar 2024 12:18:10 +0000 (UTC)',
     *     ),
     *     8 =>
     *     array (
     *       'Name' => 'Received',
     *       'Value' => 'by mail-lf1-f51.google.com with SMTP id 2adb3069b0e04-513cf9bacf1so4773866e87.0        for <370c69ad9e41d616fc914b3c60250224@inbound.postmarkapp.com>; Sun, 24 Mar 2024 05:18:10 -0700 (PDT)',
     *     ),
     *     9 =>
     *     array (
     *       'Name' => 'DKIM-Signature',
     *       'Value' => 'v=1; a=rsa-sha256; c=relaxed/relaxed;        d=test.de; s=google; t=1711282689; x=1711887489; darn=inbound.postmarkapp.com;        h=to:subject:message-id:date:from:in-reply-to:references:mime-version         :from:to:cc:subject:date:message-id:reply-to;        bh=NvjmqLXF/5L5ZrpToR/6FgVOhTOGC9j0/B2Na5Ke6J8=;        b=AMXIEoh6yGrOT6X3eBBClQ3NXFNuEoqxeM6aPONsqbpShAcT24iAJmqXylaLHv3fyX         Hm6mwp3a029NnrLP/VRyKZbzIMBN2iycidtrEMXF/Eg2e42Q/08/2dZ7nxH6NqE/jz01         3M7qvwHvuoZ2Knhj7rnZc6I5m/nFxBsZc++Aj0Vv9sFoWZZooqAeTXbux1I5NyE17MrL         D6byca43iINARZN7XOkoChRRZoZbOqZEtc2Va5yw7v+aYguLB4HHrIFC7G+L8hAJ0IAo         3R3DFeBw58M1xtxXCREI8Y6qMQTw60XyFw0gVmZzqR4hZiTerBSJJsZLZOBgmXxq3WLS         +xVQ==',
     *     ),
     *     10 =>
     *     array (
     *       'Name' => 'X-Google-DKIM-Signature',
     *       'Value' => 'v=1; a=rsa-sha256; c=relaxed/relaxed;        d=1e100.net; s=20230601; t=1711282689; x=1711887489;        h=to:subject:message-id:date:from:in-reply-to:references:mime-version         :x-gm-message-state:from:to:cc:subject:date:message-id:reply-to;        bh=NvjmqLXF/5L5ZrpToR/6FgVOhTOGC9j0/B2Na5Ke6J8=;        b=uKoMhir+MX/wycNEr29Sffj45ooKksCJ1OfSRkIIGHk0rnHn8Vh+c7beYipwRPW4F2         h46K64vtIX00guYMdL2Qo2eY96+wALTqHCy67PGhvotVTROz21yxjx62pCDPGs5tefOu         IkyxoybpIK8zAfLoDTd9p2GIrr5brKJyB2w1NQc1htxTQ5D4RgBxUAOKv4uVEr8r47iA         MIo5d8/AifA+vCOAh7iJ7EmvDQ1R+guhQyH9m1Jo8PLapiYuHXggpBJvooyGuflKqbnt         gJ/dscEr4d5aWJbw/x1dmIJ5gyJPGdBWq8NRqV/qbkXQW3H/gylifDUPXbki+EQBD5Yu         EuLQ==',
     *     ),
     *     11 =>
     *     array (
     *       'Name' => 'X-Gm-Message-State',
     *       'Value' => 'AOJu0Yxpbp1sRh17lNzg+pLnIx1jCn8ZFJQMgFuHK+6Z8RqFS5KKKTxR	8onpEbxWYYVUbrJFExNBHPD/3jdxqifCVVNaDmbpwHgmW5lHLJmA5vYRq5NFZ9OA6zKx/N6Gipr	iXE4fXmSqghFNTzy9V/RT08Zp+F5RiFh/Ta6ltQl8XfCPFfSawLz6cagUgt8bBuF4RqdrYmWwzj	ty86V5Br1htRNEFYivoXnNmaRcsD0tca1D23ny62O6RwWugrj1IpAYhViNyTZAWu+loKgfjJJoI	MsyiSU=',
     *     ),
     *     12 =>
     *     array (
     *       'Name' => 'X-Google-Smtp-Source',
     *       'Value' => 'AGHT+IEdtZqbVI6j7WLeaSL3dABGSnWIXaSjbYqXvFvE2H+f2zsn0gknQ4OdTJecQRCabpypVF2ue91Jb7aKl6RiyEQ=',
     *     ),
     *     13 =>
     *     array (
     *       'Name' => 'X-Received',
     *       'Value' => 'by 2002:a19:385a:0:b0:513:c876:c80a with SMTP id d26-20020a19385a000000b00513c876c80amr2586776lfj.34.1711282689140; Sun, 24 Mar 2024 05:18:09 -0700 (PDT)',
     *     ),
     *     14 =>
     *     array (
     *       'Name' => 'MIME-Version',
     *       'Value' => '1.0',
     *     ),
     *     15 =>
     *     array (
     *       'Name' => 'References',
     *       'Value' => '<CADfEuNsNFmNNCJDPjpS36amoLv2XEm41HmgYJT7Tj=R96PkxnA@mail.gmail.com>',
     *     ),
     *     16 =>
     *     array (
     *       'Name' => 'In-Reply-To',
     *       'Value' => '<CADfEuNsNFmNNCJDPjpS36amoLv2XEm41HmgYJT7Tj=R96PkxnA@mail.gmail.com>',
     *     ),
     *     17 =>
     *     array (
     *       'Name' => 'Message-ID',
     *       'Value' => '<CADfEuNvyCLsnp=CwJ3BF=-L6rn=o+DmUOPP6Cp4F-SO0p0hVwQ@mail.gmail.com>',
     *     ),
     *   ),
     *   'Attachments' =>
     *   array (
     *   ),
     *  )
     * @return void
     */
    public function handle()
    {
        $recipient = explode("|", $this->input)[0];

        // match company
        $company = MultiDB::findAndSetDbByExpenseMailbox($recipient);
        if (!$company) {
            Log::info('[ProcessMailgunInboundWebhook] unknown Expense Mailbox occured while handling an inbound email from postmark: ' . $recipient);
            return;
        }

        // fetch message from postmark-api
        $company_postmark_secret = $company->settings?->email_sending_method === 'client_postmark' && $company->settings?->postmark_secret ? $company->settings?->postmark_secret : null;
        if (!($company_postmark_secret) && !(config('services.postmark.domain') && config('services.postmark.secret')))
            throw new \Error("[ProcessMailgunInboundWebhook] no postmark credenitals found, we cannot get the attachements and files");

        $mail = null;
        if ($company_postmark_secret) {

            $credentials = $company_postmark_domain . ":" . $company_postmark_secret . "@";
            $messageUrl = explode("|", $this->input)[1];
            $messageUrl = str_replace("http://", "http://" . $credentials, $messageUrl);
            $messageUrl = str_replace("https://", "https://" . $credentials, $messageUrl);

            try {
                $mail = json_decode(file_get_contents($messageUrl));
            } catch (\Error $e) {
                if (config('services.postmark.secret')) {
                    Log::info("[ProcessMailgunInboundWebhook] Error while downloading with company credentials, we try to use default credentials now...");

                    $credentials = config('services.postmark.domain') . ":" . config('services.postmark.secret') . "@";
                    $messageUrl = explode("|", $this->input)[1];
                    $messageUrl = str_replace("http://", "http://" . $credentials, $messageUrl);
                    $messageUrl = str_replace("https://", "https://" . $credentials, $messageUrl);
                    $mail = json_decode(file_get_contents($messageUrl));

                } else
                    throw $e;
            }

        } else {

            $credentials = config('services.postmark.domain') . ":" . config('services.postmark.secret') . "@";
            $messageUrl = explode("|", $this->input)[1];
            $messageUrl = str_replace("http://", "http://" . $credentials, $messageUrl);
            $messageUrl = str_replace("https://", "https://" . $credentials, $messageUrl);
            $mail = json_decode(file_get_contents($messageUrl));

        }

        // prepare data for ingresEngine
        $ingresEmail = new IngresEmail();

        $ingresEmail->from = $mail->sender;
        $ingresEmail->to = $recipient; // usage of data-input, because we need a single email here
        $ingresEmail->subject = $mail->Subject;
        $ingresEmail->body = $mail->{"body-html"};
        $ingresEmail->text_body = $mail->{"body-plain"};
        $ingresEmail->date = Carbon::createFromTimeString($mail->Date);

        // parse documents as UploadedFile from webhook-data
        foreach ($mail->attachments as $attachment) { // prepare url with credentials before downloading :: https://github.com/postmark/postmark.js/issues/24

            // download file and save to tmp dir
            if ($company_postmark_domain && $company_postmark_secret) {

                try {

                    $credentials = $company_postmark_domain . ":" . $company_postmark_secret . "@";
                    $url = $attachment->url;
                    $url = str_replace("http://", "http://" . $credentials, $url);
                    $url = str_replace("https://", "https://" . $credentials, $url);
                    $ingresEmail->documents[] = TempFile::UploadedFileFromUrl($url, $attachment->name, $attachment->{"content-type"});

                } catch (\Error $e) {
                    if (config('services.postmark.secret')) {
                        Log::info("[ProcessMailgunInboundWebhook] Error while downloading with company credentials, we try to use default credentials now...");

                        $credentials = config('services.postmark.domain') . ":" . config('services.postmark.secret') . "@";
                        $url = $attachment->url;
                        $url = str_replace("http://", "http://" . $credentials, $url);
                        $url = str_replace("https://", "https://" . $credentials, $url);
                        $ingresEmail->documents[] = TempFile::UploadedFileFromUrl($url, $attachment->name, $attachment->{"content-type"});

                    } else
                        throw $e;
                }

            } else {

                $credentials = config('services.postmark.domain') . ":" . config('services.postmark.secret') . "@";
                $url = $attachment->url;
                $url = str_replace("http://", "http://" . $credentials, $url);
                $url = str_replace("https://", "https://" . $credentials, $url);
                $ingresEmail->documents[] = TempFile::UploadedFileFromUrl($url, $attachment->name, $attachment->{"content-type"});

            }

        }

        // perform
        (new IngresEmailEngine($ingresEmail))->handle();
    }
}
