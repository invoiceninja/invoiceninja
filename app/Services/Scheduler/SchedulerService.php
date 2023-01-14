<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Scheduler;

use App\Mail\Client\ClientStatement;
use App\Models\Client;
use App\Models\Scheduler;
use App\Utils\Ninja;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SchedulerService
{
    use MakesHash;
    use MakesDates;

    private string $method;

    private Client $client;

    public function __construct(public Scheduler $scheduler) {}

    /**
     * Called from the TaskScheduler Cron
     * 
     * @return void 
     */
    public function runTask(): void
    {
        $this->{$this->scheduler->template}();
    }

    private function client_statement()
    {   
        $query = Client::query()
                        ->where('company_id', $this->scheduler->company_id);

        //Email only the selected clients
        if(count($this->scheduler->parameters['clients']) >= 1)
            $query->where('id', $this->transformKeys($this->scheduler->parameters['clients']));
     

        $query->cursor()
            ->each(function ($_client){

            $this->client = $_client;
            $statement_properties = $this->calculateStatementProperties();

           //work out the date range 
            $pdf = $_client->service()->statement($statement_properties);

            $mail_able_envelope = $this->buildMailableData($pdf);

            Mail::send($mail_able_envelope);

    //calculate next run dates;

        });

    }

    private function calculateStatementProperties()
    {
        $start_end = $this->calculateStartAndEndDates();

        $this->client_start_date = $this->translateDate($start_end[0], $this->client->date_format(), $this->client->locale());
        $this->client_end_date = $this->translateDate($start_end[1], $this->client->date_format(), $this->client->locale());

        return [
            'start_date' =>$start_end[0], 
            'end_date' =>$start_end[1], 
            'show_payments_table' => $this->scheduler->parameters['show_payments_table'], 
            'show_aging_table' => $this->scheduler->parameters['show_aging_table'], 
            'status' => $this->scheduler->parameters['status']
        ];

    }

    private function calculateStartAndEndDates()
    {
        return match ($this->scheduler->parameters['date_range']) {
            'this_month' => [now()->firstOfMonth()->format('Y-m-d'), now()->lastOfMonth()->format('Y-m-d')],
            'this_quarter' => [now()->firstOfQuarter()->format('Y-m-d'), now()->lastOfQuarter()->format('Y-m-d')],
            'this_year' => [now()->firstOfYear()->format('Y-m-d'), now()->lastOfYear()->format('Y-m-d')],
            'previous_month' => [now()->subMonth()->firstOfMonth()->format('Y-m-d'), now()->subMonth()->lastOfMonth()->format('Y-m-d')],
            'previous_quarter' => [now()->subQuarter()->firstOfQuarter()->format('Y-m-d'), now()->subQuarter()->lastOfQuarter()->format('Y-m-d')],
            'previous_year' => [now()->subYear()->firstOfYear()->format('Y-m-d'), now()->subYear()->lastOfYear()->format('Y-m-d')],
            'custom_range' => [$this->scheduler->parameters['start_date'], $this->scheduler->parameters['end_date']],
            'default' => [now()->firstOfMonth()->format('Y-m-d'), now()->lastOfMonth()->format('Y-m-d')],
        };
    }

    private function buildMailableData($pdf)
    {
        App::setLocale($this->client->locale());
        $primary_contact = $this->client->primary_contact()->first();
        $settings = $this->client->getMergedSettings();

        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($settings));

        $data = [
            'to' => [new Address($this->client->present()->email(), $this->client->present()->name())],
            'from' => new Address($this->client->company->owner()->email, $this->client->company->owner()->name()),
            'reply_to' => [$this->buildReplyTo($settings)],
            'cc' => $this->buildCc($settings),
            'bcc' => $this->buildBcc($settings),
            'subject' => ctrans('texts.your_statement'),
            'body' => ctrans('texts.client_statement_body', ['start_date' => $this->client_start_date, 'end_date' => $this->client_end_date]),
            'attachments' => [
                ['name' => ctrans('texts.statement') . ".pdf", 'file' => base64_encode($pdf)],
            ],
            'company_key' => $this->client->company->company_key,
            'settings' => $settings,
            'whitelabel' => $this->client->user->account->isPaid() ? true : false,
            'logo' => $this->client->company->present()->logo($settings),
            'signature' => $settings->email_signature,
            'company' => $this->client->company,
            'greeting' => ctrans('texts.email_salutation', ['name' => $primary_contact->present()->name()]),
        ];

        return new ClientStatement($data);

    }

    private function buildReplyTo($settings)
    {

        $reply_to_email = str_contains($settings->reply_to_email, "@") ? $settings->reply_to_email : $this->client->company->owner()->email;

        $reply_to_name = strlen($settings->reply_to_name) > 3 ? $settings->reply_to_name : $this->client->company->owner()->present()->name();

        return new Address($reply_to_email, $reply_to_name);

    }

    private function buildBcc($settings): array
    {
        $bccs = false;
        $bcc_array = [];

        if (strlen($settings->bcc_email) > 1) {

            if (Ninja::isHosted() && $this->client->company->account->isPaid()) {
                $bccs = array_slice(explode(',', str_replace(' ', '', $settings->bcc_email)), 0, 2);
            } else {
                $bccs(explode(',', str_replace(' ', '', $settings->bcc_email)));
            }
        }

        if(!$bccs)
            return $bcc_array;

        foreach($bccs as $bcc)
        {
            $bcc_array[] = new Address($bcc);
        }
        
        return $bcc_array;

    }

    private function buildCc($settings)
    {
        return [
        
        ];
    }


}