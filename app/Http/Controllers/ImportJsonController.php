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

        if ($request->metadata) {

            $metadata = $this->handleChunkedUpload($request);

            if (!isset($metadata['uploaded_filepath'])) {

                return response()->json([
                        'success' => true,
                        'message' => 'Chunk uploaded successfully',
                        'chunk' => $metadata['currentChunk'],
                        'totalChunks' => $metadata['totalChunks'],
                        'fileName' => $metadata['fileName']
                    ], 200);

            }

            $file_location = $metadata['uploaded_filepath'];
        } else {

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

        return response()->json(array_merge(['message' => 'Processing','success' => true], $metadata), 200);
    }

    private function handleChunkedUploadX(ImportJsonRequest $request)
    {
        $metadata = json_decode($request->metadata, true);
        
        // Validate metadata structure
        if (!isset($metadata['fileHash'], $metadata['fileName'], $metadata['totalChunks'], $metadata['currentChunk'])) {
            throw new \InvalidArgumentException('Invalid metadata structure');
        }

        // Sanitize and validate file hash (should be alphanumeric)
        if (!preg_match('/^[a-zA-Z0-9]+$/', $metadata['fileHash'])) {
            throw new \InvalidArgumentException('Invalid file hash format');
        }

        // Sanitize and validate filename
        $safeFileName = basename($metadata['fileName']);
        if ($safeFileName !== $metadata['fileName']) {
            throw new \InvalidArgumentException('Invalid filename');
        }

        // Validate chunk number format
        if (!is_numeric($metadata['currentChunk']) || $metadata['currentChunk'] < 0) {
            throw new \InvalidArgumentException('Invalid chunk number');
        }

        // Validate total chunks
        if (!is_numeric($metadata['totalChunks']) || $metadata['totalChunks'] <= 0 || $metadata['totalChunks'] > 1000) {
            throw new \InvalidArgumentException('Invalid total chunks');
        }

        // Validate file type
        $chunk = $request->file('file');
        if (!$chunk || !$chunk->isValid()) {
            throw new \InvalidArgumentException('Invalid file chunk');
        }

        // Create a secure base directory path
        $baseDir = storage_path('app/tmp/uploads');
        $tempPath = $baseDir . '/' . $metadata['fileHash'] . '/chunks';

        // Secure directory creation
        if (!is_dir($tempPath)) {
            if (!mkdir($tempPath, 0755, true)) {
                throw new \RuntimeException('Failed to create directory');
            }
        }

        // Secure path for chunk
        $chunkPath = $tempPath . '/' . (int)$metadata['currentChunk'];

        // Validate file size before saving
        $maxChunkSize = 5 * 1024 * 1024; // 5MB
        if ($chunk->getSize() > $maxChunkSize) {
            throw new \InvalidArgumentException('Chunk size exceeds limit');
        }

        // Save chunk securely
        if (!$chunk->move($tempPath, (string)$metadata['currentChunk'])) {
            throw new \RuntimeException('Failed to save chunk');
        }

        $uploadedChunks = count(glob($tempPath . '/*'));

        if ($uploadedChunks >= $metadata['totalChunks']) {
            try {
                // Combine chunks securely
                $outputPath = $baseDir . '/' . $metadata['fileHash'] . '/' . $safeFileName;
                $outputDir = dirname($outputPath);
                
                if (!is_dir($outputDir)) {
                    mkdir($outputDir, 0755, true);
                }

                $handle = fopen($outputPath, 'wb');
                if ($handle === false) {
                    throw new \RuntimeException('Failed to create output file');
                }

                // Combine chunks with validation
                for ($i = 0; $i < $metadata['totalChunks']; $i++) {
                    $chunkFile = $tempPath . '/' . $i;
                    if (!file_exists($chunkFile)) {
                        throw new \RuntimeException("Missing chunk: {$i}");
                    }
                    
                    $chunkContent = file_get_contents($chunkFile);
                    if ($chunkContent === false) {
                        throw new \RuntimeException("Failed to read chunk: {$i}");
                    }
                    
                    if (fwrite($handle, $chunkContent) === false) {
                        throw new \RuntimeException("Failed to write chunk: {$i}");
                    }
                }

                fclose($handle);

                // Store in final location
                $disk = Ninja::isHosted() ? 'backup' : config('filesystems.default');
                $finalPath = 'migrations/' . $safeFileName;

                Storage::disk($disk)->put(
                    $finalPath,
                    file_get_contents($outputPath),
                    ['visibility' => 'private']
                );

                // Clean up
                $this->secureDeleteDirectory($baseDir . '/' . $metadata['fileHash']);

                $metadata['uploaded_filepath'] = $finalPath;
                return $metadata;

            } catch (\Exception $e) {
                // Clean up on error
                $this->secureDeleteDirectory($baseDir . '/' . $metadata['fileHash']);
                throw $e;
            }
        }

        return $metadata;
    }

    /**
     * Securely delete a directory and its contents
     */
    private function secureDeleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        rmdir($dir);
    }

    private function handleChunkedUpload(ImportJsonRequest $request)
    {
        $metadata = json_decode($request->metadata, true);
        
        // Validate metadata structure
        if (!isset($metadata['fileHash'], $metadata['fileName'], $metadata['totalChunks'], $metadata['currentChunk'])) {
            throw new \InvalidArgumentException('Invalid metadata structure');
        }

        // Sanitize and validate file hash (should be alphanumeric)
        if (!preg_match('/^[a-zA-Z0-9]+$/', $metadata['fileHash'])) {
            throw new \InvalidArgumentException('Invalid file hash format');
        }

        // Sanitize and validate filename
        $safeFileName = basename($metadata['fileName']);
        if ($safeFileName !== $metadata['fileName']) {
            throw new \InvalidArgumentException('Invalid filename');
        }

        // Validate chunk number format
        if (!is_numeric($metadata['currentChunk']) || $metadata['currentChunk'] < 0) {
            throw new \InvalidArgumentException('Invalid chunk number');
        }

        // Validate total chunks
        if (!is_numeric($metadata['totalChunks']) || $metadata['totalChunks'] <= 0 || $metadata['totalChunks'] > 1000) {
            throw new \InvalidArgumentException('Invalid total chunks');
        }

        // Validate file type
        $chunk = $request->file('file');
        if (!$chunk || !$chunk->isValid()) {
            throw new \InvalidArgumentException('Invalid file chunk');
        }

        // Validate file size before saving
        $maxChunkSize = 5 * 1024 * 1024; // 5MB
        if ($chunk->getSize() > $maxChunkSize) {
            throw new \InvalidArgumentException('Chunk size exceeds limit');
        }

        $disk = Ninja::isHosted() ? 'backup' : config('filesystems.default');
        
        // Store chunk in S3 with unique path
        $chunkKey = "tmp/chunks/{$metadata['fileHash']}/chunk-{$metadata['currentChunk']}";
        
        Storage::disk($disk)->put(
            $chunkKey,
            file_get_contents($chunk->getRealPath()),
            ['visibility' => 'private']
        );

        // Check if all chunks are uploaded by listing S3 objects
        $chunkPrefix = "tmp/chunks/{$metadata['fileHash']}/";
        $uploadedChunks = collect(Storage::disk($disk)->files($chunkPrefix))
            ->filter(function($file) {
                return str_contains(basename($file), 'chunk-');
            })
            ->count();

        if ($uploadedChunks >= $metadata['totalChunks']) {
            try {
                // Combine chunks from S3
                $finalPath = "migrations/{$safeFileName}";
                $this->combineChunksFromS3($disk, $metadata['fileHash'], $metadata['totalChunks'], $finalPath);
                
                // Clean up
                $this->cleanupS3Chunks($disk, $metadata['fileHash']);
                
                $metadata['uploaded_filepath'] = $finalPath;
                return $metadata;

            } catch (\Exception $e) {
                // Clean up on error
                $this->cleanupS3Chunks($disk, $metadata['fileHash']);
                throw $e;
            }
        }

        return $metadata;
    }

    private function combineChunksFromS3(string $disk, string $fileHash, int $totalChunks, string $finalPath): void
    {
        // Create a temporary local file to combine chunks
        $tempFile = tempnam(sys_get_temp_dir(), 'chunk_combine_');
        
        try {
            $handle = fopen($tempFile, 'wb');
            if ($handle === false) {
                throw new \RuntimeException('Failed to create temporary file');
            }

            // Download and combine chunks in order
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkKey = "tmp/chunks/{$fileHash}/chunk-{$i}";
                
                if (!Storage::disk($disk)->exists($chunkKey)) {
                    throw new \RuntimeException("Missing chunk: {$i}");
                }
                
                $chunkContent = Storage::disk($disk)->get($chunkKey);
                if ($chunkContent === null) {
                    throw new \RuntimeException("Failed to read chunk: {$i}");
                }
                
                if (fwrite($handle, $chunkContent) === false) {
                    throw new \RuntimeException("Failed to write chunk: {$i}");
                }
            }

            fclose($handle);

            // Upload combined file to final location
            Storage::disk($disk)->put(
                $finalPath,
                file_get_contents($tempFile),
                ['visibility' => 'private']
            );

        } finally {
            // Clean up temporary file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    private function cleanupS3Chunks(string $disk, string $fileHash): void
    {
        $chunkPrefix = "tmp/chunks/{$fileHash}/";
        
        // Get all chunk files for this upload
        $chunkFiles = Storage::disk($disk)->files($chunkPrefix);
        
        // Delete all chunk files
        if (!empty($chunkFiles)) {
            Storage::disk($disk)->delete($chunkFiles);
        }
    }
}
