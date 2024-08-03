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

use App\Models\Expense;
use App\Services\EDocument\Imports\ZugferdEDocument;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ImportEDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $deleteWhenMissingModels = true;
    private string $file_name;
    private readonly string $file_content;

    public function __construct(string $file_content, string $file_name)
    {
        $this->file_content = $file_content;
        $this->file_name = $file_name;
    }

    /**
     * Execute the job.
     *
     * @return Expense
     * @throws \Exception
     */
    public function handle(): Expense
    {
        if (str_contains($this->file_name, ".xml")){
            switch (true) {
                case stristr($this->file_content, "urn:cen.eu:en16931:2017"):
                case stristr($this->file_content, "urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0"):
                case stristr($this->file_content, "urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_2.1"):
                case stristr($this->file_content, "urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_2.0"):
                    return (new ZugferdEDocument($this->file_content, $this->file_name))->run();
                default:
                    throw new Exception("E-Invoice standard not supported");
            }
        }
        else {
            throw new Exception("File type not supported");
        }

    }
}
