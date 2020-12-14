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

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Import\PreImportRequest;
use App\Import\Definitions

class InvoiceMap
{

    public static function importable()
    {
        return [
            0 => 'number',
            1 => 'user_id',
            2 => 'amount',
            3 => 'balance',
            4 => 'client_id',
            5 => 'status_id',
            6 => 'is_deleted',
            7 => 'number',
            8 => 'discount',
            9 => 'po_number',
            10 => 'date',
            11 => 'due_date',
            12 => 'terms',
            13 => 'public_notes',
            14 => 'private_notes',
            15 => 'uses_inclusive_taxes',
            16 => 'tax_name1',
            17 => 'tax_rate1',
            18 => 'tax_name2',
            19 => 'tax_rate2',
            20 => 'tax_name3',
            21 => 'tax_rate3',
            22 => 'is_amount_discount',
            23 => 'footer',
            24 => 'partial',
            25 => 'partial_due_date',
            26 => 'custom_value1',
            27 => 'custom_value2',
            28 => 'custom_value3',
            29 => 'custom_value4',
            30 => 'custom_surcharge1',
            31 => 'custom_surcharge2',
            32 => 'custom_surcharge3',
            33 => 'custom_surcharge4',
            34 => 'exchange_rate',
            35 => 'line_items',
        ]\InvoiceMap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\Csv\Reader;
use League\Csv\Statement;

class ImportController extends Controller
{

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreImportRequest $request
     * @return Response
     *
     * @OA\Post(
     *      path="/api/v1/preimport",
     *      operationId="preimport",
     *      tags={"imports"},
     *      summary="Pre Import checks - returns a reference to the job and the headers of the CSV",
     *      description="Pre Import checks - returns a reference to the job and the headers of the CSV",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\RequestBody(
     *         description="The CSV file",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="string",
     *                 format="binary"
     *             )
     *         )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns a reference to the file",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function preimport(PreImportRequest $request)
    {
        //create a reference
        $hash = Str::random(32);

        //store the csv in cache with an expiry of 10 minutes
        Cache::put($hash, base64_encode(file_get_contents($request->file('file')->getPathname())), 60);

        //parse CSV
        $csv_array = $this->getCsvData(file_get_contents($request->file('file')->getPathname()));

        $data['data'] = [
            'hash' => $hash,
            'available' => InvoiceMap::importable(),
            'headers' => array_slice($csv_array, 0, 2)
        ];

        return response()->json($data);
    }


    private function getCsvData($csvfile)
    {

        if (! ini_get('auto_detect_line_endings')) {
            ini_set('auto_detect_line_endings', '1');
        }

        $csv = Reader::createFromString($csvfile);
        $stmt = new Statement();
        $data = iterator_to_array($stmt->process($csv));

        if (count($data) > 0) {
            $headers = $data[0];

            // Remove Invoice Ninja headers
            if (count($headers) && count($data) > 4) {
                $firstCell = $headers[0];
                if (strstr($firstCell, config('ninja.app_name'))) {
                    array_shift($data); // Invoice Ninja...
                    array_shift($data); // <blank line>
                    array_shift($data); // Enitty Type Header
                }
            }
        }

        return $data;
    }
}
