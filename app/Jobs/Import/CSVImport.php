<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Import;

use App\Libraries\MultiDB;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class CSVImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invoice;

    public $company;

    public $hash;

    public $entity_type;

    public $skip_headers;

    public function __construct(Request $request, Company $company)
    {
        $this->request = $request;
    
        $this->company = $company;

        $this->hash = $request->input('hash');

        $this->entity_type = $request->input('entity_type');

        $this->skip_headers = $request->input('skip_headers');
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {
        MultiDB::setDb($this->company->db);

    }

    public function failed($exception)
    {

    }

    private function getCsv()
    {
        $base64_encoded_csv = Cache::get($this->hash);

        return base64_decode($base64_encoded_csv);
    }
}
