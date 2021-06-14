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

namespace App\Http\Controllers;

use App\Http\Requests\Import\ImportJsonRequest;
use App\Jobs\Company\CompanyExport;
use App\Jobs\Company\CompanyImport;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use ZipArchive;

class ImportJsonController extends BaseController
{
    use MakesHash;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @OA\Post(
     *      path="/api/v1/import_json",
     *      operationId="getImportJson",
     *      tags={"import"},
     *      summary="Import data from the system",
     *      description="Import data from the system",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Response(
     *          response=200,
     *          description="success",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function import(ImportJsonRequest $request)
    {

        $import_file = $request->file('files');

        $contents = $this->unzipFile($import_file->getPathname());

        $hash = Str::random(32);
    
        nlog($hash);

        Cache::put( $hash, base64_encode( $contents ), 3600 );

        CompanyImport::dispatch(auth()->user()->getCompany(), auth()->user(), $hash, $request->except('files'))->delay(now()->addMinutes(1));

        return response()->json(['message' => 'Processing'], 200);

    }

    private function unzipFile($file_contents)
    {
        $zip = new ZipArchive();
        $archive = $zip->open($file_contents);

        $filename = pathinfo($file_contents, PATHINFO_FILENAME);
        $zip->extractTo(public_path("storage/backups/{$filename}"));
        $zip->close();
        $file_location = public_path("storage/backups/$filename/backup.json");

        if (! file_exists($file_location)) 
            throw new NonExistingMigrationFile('Backup file does not exist, or is corrupted.');
        
        $data = file_get_contents($file_location);

        unlink($file_contents);
        unlink($file_location);

        return $data;
    }
}
