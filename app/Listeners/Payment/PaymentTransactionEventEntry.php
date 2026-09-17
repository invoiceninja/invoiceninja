<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Listeners\Payment;

use App\Libraries\MultiDB;
use App\Listeners\Invoice\InvoiceTransactionEventEntryCash;
use App\Models\TransactionEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class PaymentTransactionEventEntry implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public int $tries = 5;

    public int $delay = 9;

    /**
     * @param array<int, array{
     *     source_event_id:int,
     *     paymentable_id:int,
     *     invoice_id:int,
     *     amount:float,
     *     effective_date:string,
     *     kind:string,
     *     correction_key:string,
     *     mutation_key:string
     * }> $mutation_snapshots
     */
    public function __construct(
        private int $payment_id,
        private array $mutation_snapshots,
        private string $db,
    ) {}

    public function handle(InvoiceTransactionEventEntryCash $writer): void
    {
        MultiDB::setDb($this->db);

        foreach ($this->mutation_snapshots as $snapshot) {
            $source = TransactionEvent::query()->find($snapshot['source_event_id']);

            if (! $source
                || (int) $source->payment_id !== $this->payment_id
                || (int) data_get($source->payment_request, 'source_paymentable_id') !== (int) $snapshot['paymentable_id']) {
                throw new \RuntimeException('Payment tax source event is unavailable.');
            }

            $writer->writeCorrection(
                source: $source,
                effective_date: $snapshot['effective_date'],
                sign: -1,
                kind: $snapshot['kind'],
                correction_key: $snapshot['correction_key'],
                context: [
                    'mutation_key' => $snapshot['mutation_key'],
                    'invoice_id' => $snapshot['invoice_id'],
                ],
                amount: $snapshot['amount'],
            );
        }
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("payment_transaction_event_entry_{$this->payment_id}_{$this->db}"))
                ->releaseAfter(10)
                ->expireAfter(300),
        ];
    }

    public function failed(?\Throwable $exception): void
    {
        if ($exception) {
            report($exception);
        }
    }
}
