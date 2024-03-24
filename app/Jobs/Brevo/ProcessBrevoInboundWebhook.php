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

namespace App\Jobs\Brevo;

use App\Libraries\MultiDB;
use App\Services\IngresEmail\IngresEmail;
use App\Services\IngresEmail\IngresEmailEngine;
use App\Utils\TempFile;
use Brevo\Client\Api\InboundParsingApi;
use Brevo\Client\Configuration;
use Illuminate\Support\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;

class ProcessBrevoInboundWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    /**
     * Create a new job instance.
     * $input consists of json/serialized-array:
     *
     *         array (
     *         'Uuid' =>
     *         array (
     *             0 => 'd9f48d52-a344-42a4-9056-9733488d9fa3',
     *         ),
     *         'Recipients' =>
     *         array (
     *             0 => 'test@test.de',
     *         ),
     *         'MessageId' => '<CADfEuNvumhUdqAUa0j6MxzVp0ooMYqdb_KZ7nZqHNAfdDqwWEQ@mail.gmail.com>',
     *         'InReplyTo' => NULL,
     *         'From' =>
     *         array (
     *             'Name' => 'Max Mustermann',
     *             'Address' => 'max@mustermann.de',
     *         ),
     *         'To' =>
     *         array (
     *             0 =>
     *             array (
     *             'Name' => NULL,
     *             'Address' => 'test@test.de',
     *             ),
     *         ),
     *         'Cc' =>
     *         array (
     *         ),
     *         'Bcc' =>
     *         array (
     *         ),
     *         'ReplyTo' => NULL,
     *         'SentAtDate' => 'Sat, 23 Mar 2024 18:18:20 +0100',
     *         'Subject' => 'TEST',
     *         'Attachments' =>
     *         array (
     *             0 =>
     *             array (
     *             'Name' => 'flag--sv-1x1.svg',
     *             'ContentType' => 'image/svg+xml',
     *             'ContentLength' => 79957,
     *             'ContentID' => 'f_lu4ct6s20',
     *             'DownloadToken' => 'eyJmb2xkZXIiOiIyMDI0MDMyMzE3MTgzNi45OS43OTgwMDM4MDQiLCJmaWxlbmFtZSI6ImZsYWctLXN2LTF4MS5zdmcifQ',
     *             ),
     *         ),
     *         'Headers' =>
     *         array (
     *             'Received' => 'by mail-ed1-f51.google.com with SMTP id 4fb4d7f45d1cf-56b0af675deso3877288a12.1 for <test@test.de>; Sat, 23 Mar 2024 10:18:36 -0700 (PDT)',
     *             'DKIM-Signature' => 'v=1; a=rsa-sha256; c=relaxed/relaxed; d=mustermann.de; s=google; t=1711214316; x=1711819116; darn=test.de; h=to:subject:message-id:date:from:mime-version:from:to:cc:subject :date:message-id:reply-to; bh=eBSl5M0zvmTd+dFXGXMMSWrQ4nCvUdyVx+1Xpl+YuX8=; b=ackw3d+qTvZk4JKxomvH626MvfwmH23mikOUc2hWwYiO6unmQgPs2w5spnkmD9aCZ9 G+3nPSYKntugOmqWstZH3z4B063U4Y6j5hTc19WtCyyb9UR+XD+C6L10yc6ez8QUhlZT uAGqDoJ+E8+dBxiMul2pow19lC88t3QxRXU+i8zScniV7SFkwzziCEODaB61yI0DXsZB bUkx5Gx6cztKaNVF2QgguF2nQnJFUnD2nabVFsihyJ5r6y61rkSM/YTfMJuES772lnhv IeF+vwiFNEPKafrchce6YJcvo5Vd5lYFK4LtHyCy3mwJpX2QY+WnWAfferZ2YfgEL0Sf K3Pw==',
     *             'X-Google-DKIM-Signature' => 'v=1; a=rsa-sha256; c=relaxed/relaxed; d=1e100.net; s=20230601; t=1711214316; x=1711819116; h=to:subject:message-id:date:from:mime-version:x-gm-message-state :from:to:cc:subject:date:message-id:reply-to; bh=eBSl5M0zvmTd+dFXGXMMSWrQ4nCvUdyVx+1Xpl+YuX8=; b=fg4tXZnstRBexYlC6MD7C7is0kQj+xY66cSJ78tSa7PtSFQzY0zajDMsepMCGiiWmN /Pc/tRtk53pru/OtfzRT9pbM6mhM1arIt+QaQBQGU5xZVV5JXfPmdnPzXqAbQztyeHrk UcEkz+qDN3JNoidw2dJhhdt5MxdKssR572NwtBrn/rN7f1o/ThWzEz+P0o06GVBpxVYP wM0EkvcJj2SUOcn36kmp1ccbMUwYCU2h1JmniEFY8RTqu2il13iXoBvG4YPxe0c0hJ6z zw1N5rONeQM113N1rpbQzS1QLSngczuOhN24M3TOwrHJIec/BxrOW6KWl/uPUqiZAf65 f0tg==',
     *             'X-Gm-Message-State' => 'AOJu0YzKhR1HY1oUXoq++LLpl6UOz1S60NfPxuPXBLcP+6aACYle8rqQ fYHe2rQYTpg4KWiOswu858STOW8qmiewXD6gH/LbmEFs7sknRyDPNr/+L0cv828A3o+SOvXu3uP SY6H1aNSwIpqTRhJ+nNjTuSUpuSoABd9fYXFwPuivV0DtBhoVmpE=',
     *             'X-Google-Smtp-Source' => 'AGHT+IHdA9ZhW0dQxgOYx2OXBGmu4pzSR/zwJ0vcPNXFSqttKCPS2oTw1a9b2mMdhyUeoRAwP5TmhHlAtqUUrOPwkgg=',
     *             'X-Received' => 'by 2002:a50:d74c:0:b0:567:3c07:8bbc with SMTP id i12-20020a50d74c000000b005673c078bbcmr2126401edj.21.1711214316135; Sat, 23 Mar 2024 10:18:36 -0700 (PDT)',
     *             'MIME-Version' => '1.0',
     *             'From' => 'Max Mustermann <max@mustermann.de>',
     *             'Date' => 'Sat, 23 Mar 2024 18:18:20 +0100',
     *             'Message-ID' => '<CADfEuNvumhUdqAUa0j6MxzVp0ooMYqdb_KZ7nZqHNAfdDqwWEQ@mail.gmail.com>',
     *             'Subject' => 'TEST',
     *             'To' => 'test@test.de',
     *             'Content-Type' => 'multipart/mixed',
     *         ),
     *         'SpamScore' => 2.8,
     *         'ExtractedMarkdownMessage' => 'TEST',
     *         'ExtractedMarkdownSignature' => NULL,
     *         'RawHtmlBody' => '<div dir="ltr">TEST</div>',
     *         'RawTextBody' => 'TEST',
     *         'EMLDownloadToken' => 'eyJmb2xkZXIiOiIyMDI0MDMyMzE3MTgzNi45OS43OTgwMDM4MDQiLCJmaWxlbmFtZSI6InNtdHAuZW1sIn0',
     *         ),
     *     )
     */
    public function __construct(private array $input)
    {
    }

    /**
     * Execute the job.
     *
     * TODO: insert Mail from Storage
     *
     * @return void
     */
    public function handle()
    {

        // brevo defines recipients as array, and we should check all of them, to be sure
        foreach ($this->input["Recipients"] as $recipient) {

            // match company
            $company = MultiDB::findAndSetDbByExpenseMailbox($recipient);
            if (!$company) {
                Log::info('unknown Expense Mailbox occured while handling an inbound email from brevo: ' . $recipient);
                continue;
            }

            $company_brevo_secret = $company->settings?->email_sending_method === 'client_brevo' && $company->settings?->brevo_secret ? $company->settings?->brevo_secret : null;
            if (empty ($company_brevo_secret) && empty (config('services.brevo.secret')))
                throw new \Error("no brevo credenitals found, we cannot get the attachement");

            // prepare data for ingresEngine
            $ingresEmail = new IngresEmail();

            $ingresEmail->from = $this->input["From"]["Address"];
            $ingresEmail->to = $recipient;
            $ingresEmail->subject = $this->input["Subject"];
            $ingresEmail->body = $this->input["RawHtmlBody"];
            $ingresEmail->text_body = $this->input["RawTextBody"];
            $ingresEmail->date = Carbon::createFromTimeString($this->input["SentAtDate"]);

            // parse documents as UploadedFile from webhook-data
            foreach ($this->input["Attachments"] as $attachment) {

                // download file and save to tmp dir
                if (!empty ($company_brevo_secret)) {

                    $attachment = null;
                    try {

                        $brevo = new InboundParsingApi(null, Configuration::getDefaultConfiguration()->setApiKey("api-key", $company_brevo_secret));
                        $attachment = $brevo->getInboundEmailAttachment($attachment["DownloadToken"]);

                    } catch (\Error $e) {
                        if (config('services.brevo.secret')) {
                            Log::info("Error while downloading with company credentials, we try to use defaul credentials now...");

                            $brevo = new InboundParsingApi(null, Configuration::getDefaultConfiguration()->setApiKey("api-key", config('services.brevo.secret')));
                            $attachment = $brevo->getInboundEmailAttachment($attachment["DownloadToken"]);

                        } else
                            throw $e;
                    }
                    $ingresEmail->documents[] = TempFile::UploadedFileFromRaw($attachment, $attachment["Name"], $attachment["ContentType"]);

                } else {

                    $brevo = new InboundParsingApi(null, Configuration::getDefaultConfiguration()->setApiKey("api-key", config('services.brevo.secret')));
                    $ingresEmail->documents[] = TempFile::UploadedFileFromRaw($brevo->getInboundEmailAttachment($attachment["DownloadToken"]), $attachment["Name"], $attachment["ContentType"]);

                }

            }

            (new IngresEmailEngine($ingresEmail))->handle();

        }
    }
}
