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
}
