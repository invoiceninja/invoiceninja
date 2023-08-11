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

namespace App\Services\Client;

use App\Models\Client;
use App\Models\Credit;
use App\Models\Payment;
use App\Services\Email\Email;
use App\Services\Email\EmailObject;
use App\Utils\Number;
use App\Utils\Traits\MakesDates;
use Carbon\Carbon;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\DB;

class ClientService
{
    use MakesDates;

    private string $client_start_date;

    private string $client_end_date;

    public function __construct(private Client $client)
    {
    }

    public function updateBalance(float $amount)
    {
        try {
            DB::connection(config('database.default'))->transaction(function () use ($amount) {
                $this->client = Client::withTrashed()->where('id', $this->client->id)->lockForUpdate()->first();
                $this->client->balance += $amount;
                $this->client->saveQuietly();
            }, 2);
        } catch (\Throwable $throwable) {
            nlog("DB ERROR " . $throwable->getMessage());
        }

        return $this;
    }

    public function updateBalanceAndPaidToDate(float $balance, float $paid_to_date)
    {
        try {
            DB::connection(config('database.default'))->transaction(function () use ($balance, $paid_to_date) {
                $this->client = Client::withTrashed()->where('id', $this->client->id)->lockForUpdate()->first();
                $this->client->balance += $balance;
                $this->client->paid_to_date += $paid_to_date;
                $this->client->saveQuietly();
            }, 2);
        } catch (\Throwable $throwable) {
            nlog("DB ERROR " . $throwable->getMessage());
        }
   
        return $this;
    }

    public function updatePaidToDate(float $amount)
    {
        DB::connection(config('database.default'))->transaction(function () use ($amount) {
            $this->client = Client::withTrashed()->where('id', $this->client->id)->lockForUpdate()->first();
            $this->client->paid_to_date += $amount;
            $this->client->saveQuietly();
        }, 2);

        return $this;
    }

    public function updatePaymentBalance()
    {
        $amount = Payment::query()->where('client_id', $this->client->id)
                        ->where('is_deleted', 0)
                        ->whereIn('status_id', [Payment::STATUS_COMPLETED, Payment::STATUS_PENDING, Payment::STATUS_PARTIALLY_REFUNDED, Payment::STATUS_REFUNDED])
                        ->sum(DB::Raw('amount - applied'));

        DB::connection(config('database.default'))->transaction(function () use ($amount) {
            $this->client = Client::withTrashed()->where('id', $this->client->id)->lockForUpdate()->first();
            $this->client->payment_balance = $amount;
            $this->client->saveQuietly();
        }, 2);

        return $this;
    }


    public function adjustCreditBalance(float $amount)
    {
        $this->client->credit_balance += $amount;

        return $this;
    }

    public function getCreditBalance() :float
    {
        $credits = Credit::withTrashed()->where('client_id', $this->client->id)
                      ->where('is_deleted', false)
                      ->where(function ($query) {
                          $query->whereDate('due_date', '<=', now()->format('Y-m-d'))
                                  ->orWhereNull('due_date');
                      })
                      ->orderBy('created_at', 'ASC');

        return Number::roundValue($credits->sum('balance'), $this->client->currency()->precision);
    }

    public function getCredits()
    {
        return Credit::query()->where('client_id', $this->client->id)
                  ->where('is_deleted', false)
                  ->where('balance', '>', 0)
                  ->where(function ($query) {
                      $query->whereDate('due_date', '<=', now()->format('Y-m-d'))
                              ->orWhereNull('due_date');
                  })
                  ->orderBy('created_at', 'ASC')->get();
    }

    public function getPaymentMethods(float $amount)
    {
        return (new PaymentMethod($this->client, $amount))->run();
    }

    public function merge(Client $mergable_client)
    {
        $this->client = (new Merge($this->client, $mergable_client))->run();

        return $this;
    }

    /**
     * Generate the client statement.
     *
     * @param array $options
     * @param bool $send_email determines if we should send this statement direct to the client
     */
    public function statement(array $options = [], bool $send_email = false)
    {
        $statement = (new Statement($this->client, $options));

        $pdf = $statement->run();

        if ($send_email) {
            // If selected, ignore clients that don't have any invoices to put on the statement.
            if (!empty($options['only_clients_with_invoices']) && $statement->getInvoices()->count() == 0) {
                return false;
            }

            $this->emailStatement($pdf, $statement->options);
            return;
        }

        return $pdf;
    }

    /**
     * Emails the statement to the client
     *
     * @param  mixed $pdf     The pdf blob
     * @param  array  $options The statement options array
     */
    private function emailStatement($pdf, array $options): void
    {
        $this->client_start_date = $this->translateDate($options['start_date'], $this->client->date_format(), $this->client->locale());
        $this->client_end_date = $this->translateDate($options['end_date'], $this->client->date_format(), $this->client->locale());
        
        $email_object = $this->buildStatementMailableData($pdf);
        Email::dispatch($email_object, $this->client->company);
    }

    /**
     * Builds and returns an EmailObject for Client Statements
     *
     * @param  mixed $pdf       The PDF to send
     * @return EmailObject      The EmailObject to send
     */
    public function buildStatementMailableData($pdf) :EmailObject
    {
        $email = $this->client->present()->email();

        $email_object = new EmailObject;
        $email_object->to = [new Address($email, $this->client->present()->name())];

        $cc_contacts = $this->client
                            ->contacts()
                            ->where('send_email', true)
                            ->where('email', '!=',  $email)
                            ->get();

        foreach ($cc_contacts as $contact) {
        
            $email_object->cc[] = new Address($contact->email, $contact->present()->name());
        
        }

        $email_object->attachments = [['file' => base64_encode($pdf), 'name' => ctrans('texts.statement') . ".pdf"]];
        $email_object->client_id = $this->client->id;
        $email_object->email_template_subject = 'email_subject_statement';
        $email_object->email_template_body = 'email_template_statement';
        $email_object->variables = [
            '$client' => $this->client->present()->name(),
            '$start_date' => $this->client_start_date,
            '$end_date' => $this->client_end_date,
        ];

        return $email_object;
    }

    /**
     * Saves the client instance
     *
     * @return Client The Client Model
     */
    public function save() :Client
    {
        $this->client->saveQuietly();

        return $this->client->fresh();
    }
}
