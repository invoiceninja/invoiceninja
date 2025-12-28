<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\VendorPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\VendorPortal\Uploads\StoreUploadRequest;
use App\Models\PurchaseOrder;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SavesDocuments;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;

class UploadController extends Controller
{
    use SavesDocuments;
    use MakesHash;

    /**
     * Main logic behind uploading the files.
     *
     * @param StoreUploadRequest $request
     * @return Response| \Illuminate\Http\JsonResponse|ResponseFactory
     */
    public function upload(StoreUploadRequest $request, PurchaseOrder $purchase_order)
    {
        $this->saveDocuments($request->getFile(), $purchase_order, $request->input('is_public', true));

        return response([], 200);
    }
}
