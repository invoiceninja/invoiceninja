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
use App\Models\Company;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use League\Csv\Reader;
use League\Csv\Statement;

class CSVImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invoice;

    public $company;

    public $hash;

    public $entity_type;

    public $skip_header;

    public $column_map;

    /*
        [hash] => 2lTm7HVR3i9Zv3y86eQYZIO16yVJ7J6l
        [entity_type] => client
        [skip_header] => 1
        [column_map] => Array
        (
            [0] => client.name
            [1] => client.user_id
            [2] => client.balance
            [3] => client.paid_to_date
            [4] => client.address1
            [5] => client.address2
            [6] => client.city
            [7] => client.state
            [8] => client.postal_code
            [9] => client.country_id
            [20] => client.currency_id
            [21] => client.public_notes
            [22] => client.private_notes
        )
     */
    public function __construct(array $request, Company $company)
    {
        $this->company = $company;

        $this->hash = $request['hash'];

        $this->entity_type = $request['entity_type'];

        $this->skip_header = $request['skip_header'];

        $this->column_map = $request['column_map'];
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

        foreach($this->getCsv() as $record) {

        }

    }

    public function failed($exception)
    {

    }

    private function getCsvData()
    {
        $base64_encoded_csv = Cache::get($this->hash);
        $csv = base64_decode($base64_encoded_csv);

        $stmt = new Statement();
        $data = iterator_to_array($stmt->process($csv));

        if (count($data) > 0) {
            $headers = $data[0];

            // Remove Invoice Ninja headers
            if (count($headers) && count($data) > 4) {
                $firstCell = $headers[0];
                if (strstr($firstCell, APP_NAME)) {
                    array_shift($data); // Invoice Ninja...
                    array_shift($data); // <blank line>
                    array_shift($data); // Enitty Type Header
                }
            }
        }

        return $data;



    }
}
