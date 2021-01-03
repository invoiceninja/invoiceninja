<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Util;

use App\Models\Company;
use App\Utils\Traits\Pdf\PdfMaker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PreviewPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, PdfMaker;

    public $company;

    private $disk;

    public $design_string;

    /**
     * Create a new job instance.
     *
     * @param $design_string
     * @param Company $company
     */
    public function __construct($design_string, Company $company)
    {
        $this->company = $company;

        $this->design_string = $design_string;

        $this->disk = $disk ?? config('filesystems.default');
    }

    public function handle()
    {
        return $this->makePdf(null, null, $this->design_string);
    }
}
