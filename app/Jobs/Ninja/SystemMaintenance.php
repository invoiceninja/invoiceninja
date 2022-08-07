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

namespace App\Jobs\Ninja;

use App\Models\Backup;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Quote;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class SystemMaintenance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        set_time_limit(0);

        nlog('Starting System Maintenance');

        if (Ninja::isHosted()) {
            return;
        }

        $delete_pdf_days = config('ninja.maintenance.delete_pdfs');

        nlog("Number of days to keep PDFs {$delete_pdf_days}");

        $delete_backup_days = config('ninja.maintenance.delete_backups');

        nlog("Number of days to keep PDFs {$delete_backup_days}");

        $this->maintainPdfs($delete_pdf_days);

        $this->maintainBackups($delete_backup_days);
    }

    private function maintainPdfs(int $delete_pdf_days)
    {
        if ($delete_pdf_days == 0) {
            return;
        }

        Invoice::with('invitations')
                ->whereBetween('created_at', [now()->subYear(), now()->subDays($delete_pdf_days)])
                ->withTrashed()
                ->cursor()
                ->each(function ($invoice) {
                    nlog("deleting invoice {$invoice->number}");

                    $invoice->service()->deletePdf();
                });

        Quote::with('invitations')
                ->whereBetween('created_at', [now()->subYear(), now()->subDays($delete_pdf_days)])
                ->withTrashed()
                ->cursor()
                ->each(function ($quote) {
                    nlog("deleting quote {$quote->number}");

                    $quote->service()->deletePdf();
                });

        Credit::with('invitations')
                ->whereBetween('created_at', [now()->subYear(), now()->subDays($delete_pdf_days)])
                ->withTrashed()
                ->cursor()
                ->each(function ($credit) {
                    nlog("deleting credit {$credit->number}");

                    $credit->service()->deletePdf();
                });
    }

    private function maintainBackups(int $delete_backup_days)
    {
        if ($delete_backup_days == 0) {
            return;
        }

        Backup::where('created_at', '<', now()->subDays($delete_backup_days))
                ->cursor()
                ->each(function ($backup) {
                    nlog("deleting {$backup->filename}");

                    if ($backup->filename) {
                        $backup->deleteFile();
                    }

                    $backup->delete();
                });
    }
}
