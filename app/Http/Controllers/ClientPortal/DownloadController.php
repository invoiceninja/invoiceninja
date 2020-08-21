<?php

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\DownloadMultipleDocumentsRequest;
use App\Http\Requests\Document\ShowDocumentRequest;
use App\Models\Document;
use App\Utils\TempFile;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Storage;
use ZipStream\Option\Archive;
use ZipStream\ZipStream;

class DownloadController extends Controller
{
    use MakesHash;

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View 
     */
    public function index()
    {
        return render('downloads.index');
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View 
     */
    public function show(ShowDocumentRequest $request, Document $download)
    {
        return render('downloads.show', [
            'document' => $download,
        ]);
    }

    /**
     * @param \App\Http\Requests\Document\ShowDocumentRequest $request 
     * @param \App\Models\Document $download 
     * @param bool $bulk 
     * @return mixed 
     */
    public function download(ShowDocumentRequest $request, Document $download)
    {
        return Storage::disk($download->disk)->download($download->url, $download->name);
    }

    public function downloadMultiple(DownloadMultipleDocumentsRequest $request)
    {
        $documents = Document::whereIn('id', $this->transformKeys($request->file_hash))
            ->where('company_id', auth('contact')->user()->company->id)
            ->get();

        $options = new Archive();

        $options->setSendHttpHeaders(true);

        $zip = new ZipStream('files.zip', $options);

        foreach ($documents as $document) {
            $zip->addFileFromPath(basename($document->filePath()), TempFile::path($document->filePath()));
        }

        $zip->finish();
    }
}
