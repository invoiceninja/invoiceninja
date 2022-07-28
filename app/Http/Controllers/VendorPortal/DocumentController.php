<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\VendorPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\VendorPortal\Documents\ShowDocumentRequest;
use App\Http\Requests\Document\DownloadMultipleDocumentsRequest;
use App\Libraries\MultiDB;
use App\Models\Document;
use App\Utils\TempFile;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DocumentController extends Controller
{
    use MakesHash;

    public const MODULE_PURCHASE_ORDERS = 16384;

    /**
     * @return Factory|View
     */
    public function index()
    {
        return render('documents.index');
    }

    /**
     * @param ShowDocumentRequest $request
     * @param Document $document
     * @return Factory|View
     */
    public function show(ShowDocumentRequest $request, Document $document)
    {
        return render('documents.vendor_show', [
            'document' => $document,
            'settings' => auth()->guard('vendor')->user()->company->settings,
            'sidebar' => $this->sidebarMenu(),
            'company' => auth()->guard('vendor')->user()->company,
        ]);
    }


    private function sidebarMenu() :array
    {
        $enabled_modules = auth()->guard('vendor')->user()->company->enabled_modules;
        $data = [];

        // TODO: Enable dashboard once it's completed.
        // $this->settings->enable_client_portal_dashboard
        // $data[] = [ 'title' => ctrans('texts.dashboard'), 'url' => 'client.dashboard', 'icon' => 'activity'];

        if (self::MODULE_PURCHASE_ORDERS & $enabled_modules) {
            $data[] = ['title' => ctrans('texts.purchase_orders'), 'url' => 'vendor.purchase_orders.index', 'icon' => 'file-text'];
        }

        // $data[] = ['title' => ctrans('texts.documents'), 'url' => 'client.documents.index', 'icon' => 'download'];

        return $data;
    }


    public function download(ShowDocumentRequest $request, Document $document)
    {
        return Storage::disk($document->disk)->download($document->url, $document->name);
    }

    public function publicDownload(string $document_hash)
    {
        MultiDB::documentFindAndSetDb($document_hash);

        $document = Document::where('hash', $document_hash)->firstOrFail();

        $headers = [];

        if (request()->input('inline') == 'true') {
            $headers = array_merge($headers, ['Content-Disposition' => 'inline']);
        }

        return Storage::disk($document->disk)->download($document->url, $document->name, $headers);
    }

    public function downloadMultiple(DownloadMultipleDocumentsRequest $request)
    {
        $documents = Document::whereIn('id', $this->transformKeys($request->file_hash))
            ->where('company_id', auth()->guard('vendor')->user()->company_id)
            ->get();

        $zipFile = new \PhpZip\ZipFile();

        try {
            foreach ($documents as $document) {
                $zipFile->addFile(TempFile::path($document->filePath()), $document->name);
            }

            $filename = now().'-documents.zip';
            $filepath = sys_get_temp_dir().'/'.$filename;

            $zipFile->saveAsFile($filepath) // save the archive to a file
                   ->close(); // close archive

           return response()->download($filepath, $filename)->deleteFileAfterSend(true);
        } catch (\PhpZip\Exception\ZipException $e) {
            // handle exception
        } finally {
            $zipFile->close();
        }
    }
}
