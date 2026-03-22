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

namespace App\Jobs\Quickbooks;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Quickbooks\QuickbooksService;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Unified job to push entities to QuickBooks.
 *
 * This job handles pushing different entity types (clients, invoices, etc.) to QuickBooks.
 * It is dispatched from model observers when:
 * - QuickBooks is configured
 * - Push events are enabled for the entity/action
 * - Sync direction allows push
 */
class PushToQuickbooks implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 3;

    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @param string $entity_type Entity type: 'client', 'invoice', etc.
     * @param int $entity_id The ID of the entity to push
     *
     * @param string $db The database name
     */
    public function __construct(
        private string $entity_type,
        private int $entity_id,
        private string $db
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        MultiDB::setDb($this->db);

        // Resolve the entity based on type
        $entity = $this->resolveEntity();

        if (!$entity) {
            return;
        }

        $company = $entity->company;

        // Double-check push is still enabled (settings might have changed)
        if (!$this->shouldPush($company, $this->entity_type)) {
            return;
        }

        $qbService = new QuickbooksService($company);

        try {
            // Dispatch to appropriate handler based on entity type
            match ($this->entity_type) {
                'client' => $this->pushClient($qbService, $entity),
                'invoice' => $this->pushInvoice($qbService, $entity),
                'product' => $this->pushProduct($qbService, $entity),
                'payment' => $this->pushPayment($qbService, $entity),
                default => nlog("QuickBooks: Unsupported entity type: {$this->entity_type}"),
            };

            $entity->refresh();
            // Note: Success activities are not logged to avoid spamming the activities table.
            // Only failures are logged as they require user attention.
        } catch (\Throwable $e) {
            nlog("Quickbooks push to Quickbooks job failed => " . $e->getMessage());
            $this->logActivityFailure($entity, $this->extractReadableError($e->getMessage()));

            return;
        }
    }

    /**
     * Resolve the entity model based on type.
     *
     * @return Client|Invoice|null
     */
    private function resolveEntity(): Client|Invoice|Product|Payment|null
    {
        return match ($this->entity_type) {
            'client' => Client::withTrashed()->find($this->entity_id),
            'invoice' => Invoice::withTrashed()->find($this->entity_id),
            'product' => Product::withTrashed()->find($this->entity_id),
            'payment' => Payment::withTrashed()->find($this->entity_id),
            default => null,
        };
    }

    /**
     * Check if push should still occur (settings might have changed since job was queued).
     *
     * @param Company $company
     * @param string $entity_type
     * @return bool
     */
    private function shouldPush(Company $company, string $entity_type): bool
    {
        return $company->shouldPushToQuickbooks($entity_type);
    }

    /**
     * Push a client to QuickBooks.
     *
     * @param QuickbooksService $qbService
     * @param Client $client
     * @return void
     */
    private function pushClient(QuickbooksService $qbService, Client $client): void
    {
        
        $qbService->client->syncToForeign([$client]);
    }

    /**
     * Push a product to QuickBooks.
     *
     * @param QuickbooksService $qbService
     * @param Product $product
     * @return void
     */
    private function pushProduct(QuickbooksService $qbService, Product $product): void
    {
        
        $qbService->product->syncToForeign([$product]);
    }


    /**
     * Push an invoice to QuickBooks.
     *
     * @param QuickbooksService $qbService
     * @param Invoice $invoice
     * @return void
     */
    private function pushInvoice(QuickbooksService $qbService, Invoice $invoice): void
    {
        // Skip invoices with no line items - QuickBooks requires at least one line item
        $line_items_count = is_array($invoice->line_items) ? count($invoice->line_items) : (is_object($invoice->line_items) ? count((array)$invoice->line_items) : 0);
        
        if ($line_items_count === 0) {
            nlog("QuickBooks: Skipping push for invoice {$invoice->id} - invoice has no line items");
            return;
        }

        $qbService->invoice->syncToForeign([$invoice]);
    }

    /**
     * Push a payment to QuickBooks.
     *
     * @param QuickbooksService $qbService
     * @param Payment $payment
     * @return void
     */
    private function pushPayment(QuickbooksService $qbService, Payment $payment): void
    {
        $qbService->payment->syncToForeign([$payment]);
    }

    private function extractReadableError(string $rawMessage): string
    {
        if (preg_match('/with body:\s*\[(.+)\]/s', $rawMessage, $matches)) {
            $body = trim($matches[1]);

            try {
                $xml = @simplexml_load_string($body);
                if ($xml !== false && isset($xml->Fault->Error)) {
                    $error = $xml->Fault->Error;
                    $message = (string) ($error->Message ?? '');
                    $detail = (string) ($error->Detail ?? '');

                    if ($message && $detail) {
                        return "{$message} - {$detail}";
                    }

                    return $message ?: $detail;
                }
            } catch (\Throwable $e) {
                // XML parsing failed, fall through
            }
        }

        $cleaned = str_replace('Request is not made successful. ', '', $rawMessage);

        return mb_substr($cleaned, 0, 500);
    }

    private function logActivityFailure($entity, string $errorMessage): void
    {
        try {
            $activity = new Activity();
            $activity->user_id = $entity->user_id ?? null;
            $activity->company_id = $entity->company_id;
            $activity->account_id = $entity->company->account_id;
            $activity->activity_type_id = Activity::QUICKBOOKS_PUSH_FAILURE;
            $activity->is_system = true;
            $activity->notes = str_replace('"', '', $errorMessage);

            match ($this->entity_type) {
                'client' => $activity->client_id = $entity->id,
                'invoice' => $activity->invoice_id = $entity->id,
                'payment' => $activity->payment_id = $entity->id,
                default => null,
            };

            $activity->save();
        } catch (\Throwable $e) {
            nlog("QuickBooks: Failed to log activity for {$this->entity_type} {$this->entity_id}: " . $e->getMessage());
        }
    }

    public function middleware(): array
    {
        return [
            new WithoutOverlapping("qbs-{$this->entity_type}-{$this->entity_id}-{$this->db}"),
        ];
    }

    public function failed($exception)
    {
        nlog("Quickbooks push to Quickbooks job failed => " . $exception->getMessage());
        config(['queue.failed.driver' => null]);

    }
}
