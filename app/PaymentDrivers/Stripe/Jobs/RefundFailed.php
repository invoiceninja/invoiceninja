<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Stripe\Jobs;

use App\Libraries\MultiDB;
use App\Models\CompanyGateway;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class RefundFailed implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 15;

    public function __construct(
        public array $stripe_request,
        public string $company_key,
        public int $company_gateway_id,
    ) {}

    public function handle(): void
    {
        MultiDB::findAndSetDbByCompanyKey($this->company_key);

        $company_gateway = CompanyGateway::query()
            ->where('id', $this->company_gateway_id)
            ->whereHas('company', fn ($query) => $query
                ->where('company_key', $this->company_key))
            ->first();

        $refund_id = $this->stripe_request['id'] ?? null;

        $references = array_filter([
            $this->stripe_request['charge'] ?? null,
            $this->stripe_request['payment_intent'] ?? null,
        ], fn ($reference) => is_string($reference) && $reference !== '');

        if (!$company_gateway || !$refund_id || !$references) {
            return;
        }

        $payment = Payment::query()
            ->withTrashed()
            ->where('company_id', $company_gateway->company_id)
            ->where('company_gateway_id', $company_gateway->id)
            ->whereIn('transaction_reference', $references)
            ->first();

        if (!$payment || $payment->is_deleted) {
            return;
        }

        $message = "Stripe refund failed to be processed. Refund ID: {$refund_id}.";
        $private_notes = (string) $payment->private_notes;

        if (str_contains($private_notes, $message)) {
            return;
        }

        $separator = $private_notes !== '' && !str_ends_with($private_notes, "\n")
            ? "\n"
            : '';

        $payment->private_notes = $private_notes . $separator . $message;
        $payment->saveQuietly();
    }

    public function middleware(): array
    {
        $reference = $this->stripe_request['payment_intent']
            ?? $this->stripe_request['charge']
            ?? $this->stripe_request['id'];

        return [
            (new WithoutOverlapping("stripe-refund-failed:{$this->company_key}:{$reference}"))
                ->releaseAfter(5)
                ->expireAfter(60),
        ];
    }
}
