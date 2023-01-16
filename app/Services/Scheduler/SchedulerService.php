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

use App\DataMapper\EmailTemplateDefaults;
use App\Mail\Client\ClientStatement;
use App\Models\Client;
use App\Models\Scheduler;
use App\Services\Email\EmailMailable;
use App\Services\Email\EmailObject;
use App\Services\Email\EmailService;
use App\Utils\Ninja;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Illuminate\Mail\Mailables\Address;
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

            $email_service = new EmailService($this->buildMailableData($pdf), $_client->company);
            $email_service->send();

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
             default => [now()->firstOfMonth()->format('Y-m-d'), now()->lastOfMonth()->format('Y-m-d')],
        };
    }

    private function buildMailableData($pdf)
    {

        $email_object = new EmailObject;
        $email_object->to = [new Address($this->client->present()->email(), $this->client->present()->name())];
        $email_object->attachments = [['file' => base64_encode($pdf), 'name' => ctrans('texts.statement') . ".pdf"]];
        $email_object->settings = $this->client->getMergedSettings();
        $email_object->company = $this->client->company;
        $email_object->client = $this->client;
        $email_object->email_template_subject = 'email_subject_statement';
        $email_object->email_template_body = 'email_template_statement';
        $email_object->variables = [
            '$client' => $this->client->present()->name(),
            '$start_date' => $this->client_start_date,
            '$end_date' => $this->client_end_date,
        ];

        return $email_object;

    }


}