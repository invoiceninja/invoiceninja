<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\EDocument;

use Exception;
use App\Models\Company;
use App\Models\Expense;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use App\Services\EDocument\Imports\ZugferdEDocument;

class ImportEDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    public function __construct(private readonly string $file_content, private string $file_name, private Company $company)
    {
    }

    /**
     * Execute the job.
     *
     * @return Expense
     * @throws \Exception
     */
    public function handle(): Expense
    {

        switch (true) {
            case stristr($this->file_content, "urn:cen.eu:en16931:2017"):
            case stristr($this->file_content, "urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0"):
            case stristr($this->file_content, "urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_2.1"):
            case stristr($this->file_content, "urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_2.0"):
                return (new ZugferdEDocument($this->file_content, $this->file_name, $this->company))->run();
            default:
                throw new Exception("E-Invoice standard not supported");
        }

    }

    public function middleware()
    {
        return [new WithoutOverlapping($this->company->company_key)];
    }

    public function failed($exception = null)
    {
        if ($exception) {
            nlog("EXCEPTION:: ImportEDocument:: ".$exception->getMessage());
        }

        config(['queue.failed.driver' => null]);
    }
}
