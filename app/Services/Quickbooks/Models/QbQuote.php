<?php

/**
 * Quote Ninja (https://quoteninja.com).
 *
 * @link https://github.com/quoteninja/quoteninja source repository
 *
 * @copyright Copyright (c) 2025. Quote Ninja LLC (https://quoteninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quickbooks\Models;

use Carbon\Carbon;
use App\Models\Quote;
use App\DataMapper\QuoteSync;
use App\Factory\QuoteFactory;
use App\Interfaces\SyncInterface;
use App\Repositories\QuoteRepository;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\Transformers\QuoteTransformer;
use App\Services\Quickbooks\Transformers\PaymentTransformer;

class QbQuote implements SyncInterface
{
    protected QuoteTransformer $quote_transformer;

    protected QuoteRepository $quote_repository;

    public function __construct(public QuickbooksService $service)
    {
        $this->quote_transformer = new QuoteTransformer($this->service->company);
        $this->quote_repository = new QuoteRepository();
    }

    public function find(string $id): mixed
    {
        return $this->service->sdk->FindById('Quote', $id);
    }

    public function syncToNinja(array $records): void
    {

        foreach ($records as $record) {

            $this->syncNinjaQuote($record);

        }

    }

    public function importToNinja(array $records): void
    {

        foreach ($records as $record) {

            $ninja_quote_data = $this->quote_transformer->qbToNinja($record);

            $client_id = $ninja_quote_data['client_id'] ?? null;

            if (is_null($client_id)) {
                continue;
            }

            if ($quote = $this->findQuote($ninja_quote_data['id'], $ninja_quote_data['client_id'])) {

                if ($quote->id) {
                    $this->qbQuoteUpdate($ninja_quote_data, $quote);
                }

                if (Quote::where('company_id', $this->service->company->id)
                    ->whereNotNull('number')
                    ->where('number', $ninja_quote_data['number'])
                    ->exists()) {
                    $ninja_quote_data['number'] = 'qb_'.$ninja_quote_data['number'].'_'.rand(1000, 99999);
                }

                $quote->fill($ninja_quote_data);
                $quote->saveQuietly();


                $quote = $quote->calc()->getQuote()->service()->markSent()->applyNumber()->createInvitations()->save();

            }

            $ninja_quote_data = false;


        }

    }

    public function syncToForeign(array $records): void
    {

    }

    private function qbQuoteUpdate(array $ninja_quote_data, Quote $quote): void
    {
        $current_ninja_quote_balance = $quote->balance;
        $qb_quote_balance = $ninja_quote_data['balance'];

        if (floatval($current_ninja_quote_balance) == floatval($qb_quote_balance)) {
            nlog('Quote balance is the same, skipping update of line items');
            unset($ninja_quote_data['line_items']);
            $quote->fill($ninja_quote_data);
            $quote->saveQuietly();
        } else {
            nlog('Quote balance is different, updating line items');
            $this->quote_repository->save($ninja_quote_data, $quote);
        }
    }

    private function findQuote(string $id, ?string $client_id = null): ?Quote
    {
        $search = Quote::query()
                            ->withTrashed()
                            ->where('company_id', $this->service->company->id)
                            ->where('sync->qb_id', $id);

        if ($search->count() == 0) {
            $quote = QuoteFactory::create($this->service->company->id, $this->service->company->owner()->id);
            $quote->client_id = (int)$client_id;

            $sync = new QuoteSync();
            $sync->qb_id = $id;
            $quote->sync = $sync;

            return $quote;
        } elseif ($search->count() == 1) {
            return $this->service->syncable('quote', \App\Enum\SyncDirection::PULL) ? $search->first() : null;
        }

        return null;

    }

    public function sync($id, string $last_updated): void
    {

        $qb_record = $this->find($id);


        if ($this->service->syncable('quote', \App\Enum\SyncDirection::PULL)) {

            $quote = $this->findQuote($id);

            nlog("Comparing QB last updated: " . $last_updated);
            nlog("Comparing Ninja last updated: " . $quote->updated_at);

            if (data_get($qb_record, 'TxnStatus') === 'Voided') {
                $this->delete($id);
                return;
            }

            if (!$quote->id) {
                $this->syncNinjaQuote($qb_record);
            } elseif (Carbon::parse($last_updated)->gt(Carbon::parse($quote->updated_at)) || $qb_record->SyncToken == '0') {
                $ninja_quote_data = $this->quote_transformer->qbToNinja($qb_record);

                $this->quote_repository->save($ninja_quote_data, $quote);

            }

        }
    }

    /**
     * syncNinjaQuote
     *
     * @param  $record
     * @return void
     */
    public function syncNinjaQuote($record): void
    {

        $ninja_quote_data = $this->quote_transformer->qbToNinja($record);

        $payment_ids = $ninja_quote_data['payment_ids'] ?? [];

        $client_id = $ninja_quote_data['client_id'] ?? null;

        if (is_null($client_id)) {
            return;
        }

        unset($ninja_quote_data['payment_ids']);

        if ($quote = $this->findQuote($ninja_quote_data['id'], $ninja_quote_data['client_id'])) {

            if ($quote->id) {
                $this->qbQuoteUpdate($ninja_quote_data, $quote);
            }
            //new quote scaffold
            $quote->fill($ninja_quote_data);
            $quote->saveQuietly();

            $quote = $quote->calc()->getQuote()->service()->markSent()->applyNumber()->createInvitations()->save();

            foreach ($payment_ids as $payment_id) {

                $payment = $this->service->sdk->FindById('Payment', $payment_id);

                $payment_transformer = new PaymentTransformer($this->service->company);

                $transformed = $payment_transformer->qbToNinja($payment);

                $ninja_payment = $payment_transformer->buildPayment($payment);
                $ninja_payment->service()->applyNumber()->save();

                $paymentable = new \App\Models\Paymentable();
                $paymentable->payment_id = $ninja_payment->id;
                $paymentable->paymentable_id = $quote->id;
                $paymentable->paymentable_type = 'quotes';
                $paymentable->amount = $transformed['applied'] + $ninja_payment->credits->sum('amount');
                $paymentable->created_at = $ninja_payment->date; //@phpstan-ignore-line
                $paymentable->save();

                $quote->service()->applyPayment($ninja_payment, $paymentable->amount);

            }

            if ($record instanceof \QuickBooksOnline\API\Data\IPPSalesReceipt) {
                $quote->service()->markPaid()->save();
            }

        }

    }

    /**
     * Deletes the quote from Ninja and sets the sync to null
     *
     * @param string $id
     * @return void
     */
    public function delete($id): void
    {
        $qb_record = $this->find($id);

        if ($this->service->syncable('quote', \App\Enum\SyncDirection::PULL) && $quote = $this->findQuote($id)) {
            $quote->sync = null;
            $quote->saveQuietly();
            $this->quote_repository->delete($quote);
        }
    }
}
