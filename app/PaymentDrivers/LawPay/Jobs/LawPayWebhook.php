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

namespace App\PaymentDrivers\LawPay\Jobs;

use App\Jobs\Util\SystemLogger;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\SystemLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class LawPayWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 3;

    public $deleteWhenMissingModels = true;

    public function __construct(
        public array $payload,
        public string $company_key,
        public int $company_gateway_id,
    ) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->company_gateway_id))
            ->expireAfter(600)];
    }

    public function handle(): void
    {
        MultiDB::findAndSetDbByCompanyKey($this->company_key);

        $company = Company::query()->where('company_key', $this->company_key)->first();

        if (!$company) {
            nlog('LawPay Webhook: Company not found for key: ' . $this->company_key);
            return;
        }

        $company_gateway = CompanyGateway::query()
            ->where('id', $this->company_gateway_id)
            ->where('company_id', $company->id)
            ->first();

        if (!$company_gateway) {
            nlog('LawPay Webhook: CompanyGateway not found: ' . $this->company_gateway_id);
            return;
        }

        $event_type = $this->payload['event'] ?? $this->payload['type'] ?? '';
        $transaction_id = $this->payload['id'] ?? $this->payload['transaction_id'] ?? '';
        $status = $this->payload['status'] ?? '';

        nlog("LawPay Webhook: event={$event_type} transaction={$transaction_id} status={$status}");

        if (!$transaction_id) {
            nlog('LawPay Webhook: No transaction ID in payload');
            return;
        }

        $payment = Payment::query()
            ->where('transaction_reference', $transaction_id)
            ->where('company_id', $company->id)
            ->first();

        if (!$payment) {
            nlog("LawPay Webhook: Payment not found for transaction: {$transaction_id}");
            return;
        }

        $this->processEvent($payment, $company_gateway, $event_type, $status);
    }

    private function processEvent(Payment $payment, CompanyGateway $company_gateway, string $event_type, string $status): void
    {
        // Normalize status to lowercase for comparison
        $status = strtolower($status);

        // Handle charge completion (important for ACH which starts as PENDING)
        if (in_array($status, ['completed', 'settled']) && $payment->status_id === Payment::STATUS_PENDING) {
            $payment->status_id = Payment::STATUS_COMPLETED;
            $payment->save();

            SystemLogger::dispatch(
                ['action' => 'webhook_completed', 'transaction' => $payment->transaction_reference],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_LAWPAY,
                $payment->client,
                $payment->company,
            );

            return;
        }

        // Handle charge failure / decline / ACH return
        if (in_array($status, ['failed', 'declined', 'returned', 'voided'])) {

            // Only process if payment hasn't already been marked as failed
            if ($payment->status_id === Payment::STATUS_FAILED) {
                return;
            }

            $payment->status_id = Payment::STATUS_FAILED;
            $payment->save();

            if ($payment->client) {
                $error_message = "LawPay payment {$status}: {$payment->transaction_reference}";

                $company_gateway->driver($payment->client)->sendFailureMail($error_message);
            }

            SystemLogger::dispatch(
                ['action' => 'webhook_failed', 'status' => $status, 'transaction' => $payment->transaction_reference],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_LAWPAY,
                $payment->client,
                $payment->company,
            );

            return;
        }

        // Handle refund events
        if (str_contains($event_type, 'refund') || $status === 'refunded') {
            $refund_amount = isset($this->payload['amount'])
                ? round($this->payload['amount'] / 100, 2)
                : $payment->amount;
            $refundReference = (string) ($this->payload['refund_id']
                ?? $this->payload['event_id']
                ?? hash('sha256', json_encode($this->payload, JSON_THROW_ON_ERROR)));

            if (collect($payment->refund_meta ?? [])->contains(
                fn(array $refund): bool => ($refund['gateway_refund_reference'] ?? null) === $refundReference,
            )) {
                return;
            }

            if (in_array($payment->status_id, [
                Payment::STATUS_COMPLETED,
                Payment::STATUS_PARTIALLY_REFUNDED,
            ], true)) {
                $refund_amount = min($refund_amount, max(0, $payment->amount - $payment->refunded));

                if ($refund_amount <= 0) {
                    return;
                }

                $invoices = $this->refundInvoices($payment, $refund_amount);
                $allocatedAmount = array_sum(array_column($invoices, 'amount'));
                $refundDate = now($payment->company->timezone()?->name ?: config('app.timezone'))->toDateString();

                if ($invoices !== []) {
                    $payment = $payment->refund([
                        'invoices' => $invoices,
                        'amount' => $allocatedAmount,
                        'date' => $refundDate,
                        'gateway_refund' => false,
                        'email_receipt' => false,
                        'via_webhook' => true,
                        'gateway_refund_reference' => $refundReference,
                    ]);
                }

                if ($refund_amount > $allocatedAmount) {
                    $payment->recordRefund($refund_amount - $allocatedAmount);
                }

                if ($invoices === []) {
                    $payment->refresh();
                    $payment->setRefundMeta([
                        'amount' => $refund_amount,
                        'date' => $refundDate,
                        'gateway_refund' => false,
                        'via_webhook' => true,
                        'gateway_refund_reference' => $refundReference,
                    ]);
                    $payment->saveQuietly();
                }

                SystemLogger::dispatch(
                    ['action' => 'webhook_refund', 'amount' => $refund_amount, 'transaction' => $payment->transaction_reference],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_SUCCESS,
                    SystemLog::TYPE_LAWPAY,
                    $payment->client,
                    $payment->company,
                );
            }

            return;
        }

        nlog("LawPay Webhook: Unhandled event type={$event_type} status={$status}");
    }

    /** @return array<int, array{invoice_id: int, amount: float}> */
    private function refundInvoices(Payment $payment, float $refundAmount): array
    {
        $remaining = $refundAmount;
        $invoices = [];

        Paymentable::query()
            ->where('payment_id', $payment->id)
            ->where('paymentable_type', 'invoices')
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->each(function (Paymentable $paymentable) use (&$remaining, &$invoices): void {
                if ($remaining <= 0) {
                    return;
                }

                $available = max(0, (float) $paymentable->amount - (float) $paymentable->refunded);
                $amount = min($remaining, $available);

                if ($amount <= 0) {
                    return;
                }

                $invoices[] = [
                    'invoice_id' => (int) $paymentable->paymentable_id,
                    'amount' => $amount,
                ];
                $remaining -= $amount;
            });

        return $invoices;
    }
}
