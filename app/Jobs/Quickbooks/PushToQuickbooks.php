<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Quickbooks;

use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Company;
use App\Services\Quickbooks\QuickbooksService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     * 
     * @param string $entity_type Entity type: 'client', 'invoice', etc.
     * @param int $entity_id The ID of the entity to push
     * @param int $company_id The company ID
     * @param string $db The database name
     * @param string $action Action type: 'create', 'update', 'status'
     * @param string|null $status Optional status for status-based pushes (e.g., invoice status: 'draft', 'sent', 'paid', 'deleted')
     */
    public function __construct(
        private string $entity_type,
        private int $entity_id,
        private int $company_id,
        private string $db,
        private string $action,
        private ?string $status = null
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        MultiDB::setDb($this->db);

        $company = Company::find($this->company_id);
        
        if (!$company) {
            return;
        }

        // Resolve the entity based on type
        $entity = $this->resolveEntity($this->entity_type, $this->entity_id);
        
        if (!$entity) {
            return;
        }

        // Double-check push is still enabled (settings might have changed)
        if (!$this->shouldPush($company, $this->entity_type, $this->action, $this->status)) {
            return;
        }

        $qbService = new QuickbooksService($company);
        
        // Dispatch to appropriate handler based on entity type
        match($this->entity_type) {
            'client' => $this->pushClient($qbService, $entity),
            'invoice' => $this->pushInvoice($qbService, $entity),
            default => nlog("QuickBooks: Unsupported entity type: {$this->entity_type}"),
        };
    }

    /**
     * Resolve the entity model based on type.
     * 
     * @param string $entity_type
     * @param int $entity_id
     * @return Client|Invoice|null
     */
    private function resolveEntity(string $entity_type, int $entity_id): Client|Invoice|null
    {
        return match($entity_type) {
            'client' => Client::find($entity_id),
            'invoice' => Invoice::find($entity_id),
            default => null,
        };
    }

    /**
     * Check if push should still occur (settings might have changed since job was queued).
     * 
     * @param Company $company
     * @param string $entity_type
     * @param string $action
     * @param string|null $status
     * @return bool
     */
    private function shouldPush(Company $company, string $entity_type, string $action, ?string $status): bool
    {
        return $company->shouldPushToQuickbooks($entity_type, $action, $status);
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
        // TODO: Implement actual push logic
        // $qbService->client->push($client, $this->action);
        
        nlog("QuickBooks: Pushing client {$client->id} to QuickBooks ({$this->action})");
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
        // Use syncToForeign to push the invoice
        $qbService->invoice->syncToForeign([$invoice]);
    }

    /**
     * Map invoice status_id and is_deleted to status string.
     * 
     * @param int $statusId
     * @param bool $isDeleted
     * @return string
     */
    private function mapInvoiceStatusToString(int $statusId, bool $isDeleted): string
    {
        if ($isDeleted) {
            return 'deleted';
        }

        return match($statusId) {
            \App\Models\Invoice::STATUS_DRAFT => 'draft',
            \App\Models\Invoice::STATUS_SENT => 'sent',
            \App\Models\Invoice::STATUS_PAID => 'paid',
            default => 'unknown',
        };
    }
}
