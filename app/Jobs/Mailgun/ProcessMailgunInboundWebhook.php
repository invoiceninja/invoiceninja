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

namespace App\Jobs\Mailgun;

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

class ProcessMailgunInboundWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    /**
     * Create a new job instance.
     * $input consists of 2 informations: recipient|messageUrl
     */
    public function __construct(private string $input)
    {
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {
        $recipient = explode("|", $this->input)[0];

        // match company
        $company = MultiDB::findAndSetDbByExpenseMailbox($recipient);
        if (!$company) {
            Log::info('unknown Expense Mailbox occured while handling an inbound email from mailgun: ' . $recipient);
            return;
        }

        // fetch message from mailgun-api
        $mailgun_domain = $company->settings?->email_sending_method === 'client_mailgun' && $company->settings?->mailgun_domain ? $company->settings?->mailgun_domain : config('services.mailgun.domain');
        $mailgun_secret = $company->settings?->email_sending_method === 'client_mailgun' && $company->settings?->mailgun_secret ? $company->settings?->mailgun_secret : config('services.mailgun.secret');
        $credentials = $mailgun_domain . ":" . $mailgun_secret . "@";
        $messageUrl = explode("|", $this->input)[1];
        $messageUrl = str_replace("http://", "http://" . $credentials, $messageUrl);
        $messageUrl = str_replace("https://", "https://" . $credentials, $messageUrl);
        $mail = json_decode(file_get_contents($messageUrl));

        // prepare data for ingresEngine
        $ingresEmail = new IngresEmail();

        $ingresEmail->from = $mail->sender;
        $ingresEmail->to = $recipient; // usage of data-input, because we need a single email here
        $ingresEmail->subject = $mail->Subject;
        $ingresEmail->body = $mail->{"body-html"};
        $ingresEmail->text_body = $mail->{"body-plain"};
        $ingresEmail->date = Carbon::createFromTimeString($mail->Date);

        // parse documents as UploadedFile from webhook-data
        foreach ($mail->attachments as $attachment) {

            // prepare url with credentials before downloading :: https://github.com/mailgun/mailgun.js/issues/24
            $url = $attachment->url;
            $url = str_replace("http://", "http://" . $credentials, $url);
            $url = str_replace("https://", "https://" . $credentials, $url);

            // download file and save to tmp dir
            $ingresEmail->documents[] = TempFile::UploadedFileFromUrl($url, $attachment->name, $attachment->{"content-type"});

        }

        // perform
        (new IngresEmailEngine($ingresEmail))->handle();
    }
}
