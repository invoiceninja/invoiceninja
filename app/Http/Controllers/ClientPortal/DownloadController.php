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
use App\Http\Requests\Document\ShowDocumentRequest;
use App\Models\Document;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Storage;

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
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse 
     */
    public function download(ShowDocumentRequest $request, Document $download)
    {
        return Storage::disk($download->disk)->download($download->url, $download->name);
    }
}
