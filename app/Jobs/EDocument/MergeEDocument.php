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

namespace App\Jobs\EDocument;

use App\Models\Invoice;
use App\Services\EDocument\ZugferdPdfMerger;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * @deprecated This job is no longer used and will be removed in a future version.
 */
class MergeEDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $deleteWhenMissingModels = true;

    public function __construct(private mixed $document, private string $pdf_file) {}

    /**
     * Execute the job.
     *
     * @return string
     */
    public function handle(): string
    {
        nlog("MergeEDocument:: handle");

        if ($this->document instanceof Invoice) {
            return (new ZugferdPdfMerger($this->document, $this->pdf_file))->handle();
        }

        return $this->pdf_file;
    }
}
