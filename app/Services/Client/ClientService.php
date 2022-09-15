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

namespace App\Services\Client;

use App\Models\Client;
use App\Models\Credit;
use App\Services\Client\Merge;
use App\Services\Client\PaymentMethod;
use App\Utils\Number;
use Illuminate\Database\Eloquent\Collection;

class ClientService
{
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function updateBalance(float $amount)
    {
        // $this->client->balance += $amount;

        \DB::connection(config('database.default'))->transaction(function () use($amount) {

            $this->client = Client::withTrashed()->where('id', $this->client->id)->lockForUpdate()->first();
            $this->client->balance += $amount;
            $this->client->save();

        }, 2);

        return $this;
    }

    public function updateBalanceAndPaidToDate(float $balance, float $paid_to_date)
    {
        // $this->client->balance += $amount;
        // $this->client->paid_to_date += $amount;

        \DB::connection(config('database.default'))->transaction(function () use($balance, $paid_to_date) {

            $this->client = Client::withTrashed()->where('id', $this->client->id)->lockForUpdate()->first();
            $this->client->balance += $balance;
            $this->client->paid_to_date += $paid_to_date;
            $this->client->save();

        }, 2);

        return $this;
    }

    public function updatePaidToDate(float $amount)
    {
        // $this->client->paid_to_date += $amount;

        \DB::connection(config('database.default'))->transaction(function () use($amount) {

            $this->client = Client::withTrashed()->where('id', $this->client->id)->lockForUpdate()->first();
            $this->client->paid_to_date += $amount;
            $this->client->save();

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
                      ->where('balance', '>', 0)
                      ->where(function ($query) {
                          $query->whereDate('due_date', '<=', now()->format('Y-m-d'))
                                  ->orWhereNull('due_date');
                      })
                      ->orderBy('created_at', 'ASC');

        return Number::roundValue($credits->sum('balance'), $this->client->currency()->precision);
    }

    public function getCredits()
    {
        return Credit::where('client_id', $this->client->id)
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
     */
    public function statement(array $options = [])
    {
        return (new Statement($this->client, $options))->run();
    }

    public function save() :Client
    {
        $this->client->save();

        return $this->client->fresh();
    }
}
