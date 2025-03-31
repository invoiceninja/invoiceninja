<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Utils\Ninja;
use Illuminate\Http\Response;
use App\Utils\Traits\MakesHash;
use App\Jobs\Company\CompanyImport;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\Import\ImportJsonRequest;

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

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $metadata = [];

        if($request->metadata) {

            $metadata = $this->handleChunkedUpload($request);

            if(!isset($metadata['uploaded_filepath'])){

                return response()->json([
                        'success' => true,
                        'message' => 'Chunk uploaded successfully',
                        'chunk' => $metadata['currentChunk'],
                        'totalChunks' => $metadata['totalChunks'],
                        'fileName' => $metadata['fileName']
                    ], 200);

           }

           $file_location = $metadata['uploaded_filepath'];
        }
        else{

            $disk = Ninja::isHosted() ? 'backup' : config('filesystems.default');

            $extension = $request->file('files')->getClientOriginalExtension();

            $parsed_filename = sprintf(
                '%s.%s',
                \Illuminate\Support\Str::random(32),
                preg_replace('/[^a-zA-Z0-9]/', '', $extension) // Sanitize extension
            );

            $file_location = $request->file('files')
                ->storeAs(
                    'migrations',
                    $parsed_filename,
                    $disk,
                );
        }

        CompanyImport::dispatch($user->company(), $user, $file_location, $request->except(['files','file']));

        unset($metadata['uploaded_filepath']);

        return response()->json(array_merge(['message' => 'Processing','success' => true], $metadata ), 200);
    }

    private function handleChunkedUpload(ImportJsonRequest $request)
    {
        
        $metadata = json_decode($request->metadata, true);
        $chunk = $request->file('file');

        $tempPath = sys_get_temp_dir()."/{$metadata['fileHash']}/app/chunks/";

        if(!is_dir($tempPath)) {
            mkdir($tempPath, 0777, true);
        }

        $chunkPath = $tempPath . '/' . $metadata['currentChunk'];
        
        file_put_contents($chunkPath, file_get_contents($chunk));

        $uploadedChunks = count(glob($tempPath . '/*'));

        if ($uploadedChunks >= $metadata['totalChunks']) {
            // Combine all chunks
            $tempFilePath = $tempPath . $metadata['fileName'];
            
            $handle = fopen($tempFilePath, 'wb');

            for ($i = 0; $i < $metadata['totalChunks']; $i++) {
                $chunkContent = file_get_contents($tempPath . '/' . $i);
                fwrite($handle, $chunkContent);
            }

            fclose($handle);
            
            $disk = Ninja::isHosted() ? 'backup' : config('filesystems.default');

            Storage::disk($disk)->put(
                'migrations/'.$metadata['fileName'],
                file_get_contents($tempFilePath),
                ['visibility' => 'private']
            );

            $this->deleteDirectory(sys_get_temp_dir()."/{$metadata['fileHash']}");

            Storage::deleteDirectory(sys_get_temp_dir()."/{$metadata['fileHash']}");

            $metadata['uploaded_filepath'] = 'migrations/'.$metadata['fileName'];
            
            return $metadata;

        }

        return $metadata;

    }

    private function deleteDirectory($dir) 
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        return rmdir($dir);
    
    }
}
