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

use App\Services\Quickbooks\QuickbooksBatchCollector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Flushes collected QuickBooks entities to a batch job.
 *
 * This job is scheduled by the BatchCollector to dispatch
 * accumulated entities after the collection window expires.
 */
class FlushQuickbooksBatch implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param string $entityType Entity type: 'client', 'invoice', etc.
     * @param string $db The database name
     * @param int $companyId The company ID
     * @param string $priority Priority level
     */
    public function __construct(
        private string $entityType,
        private string $db,
        private int $companyId,
        private string $priority = QuickbooksBatchCollector::PRIORITY_NORMAL
    ) {
        // $this->onQueue('quickbooks');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        QuickbooksBatchCollector::dispatchBatch(
            $this->entityType,
            $this->db,
            $this->companyId,
            $this->priority
        );
    }
}
