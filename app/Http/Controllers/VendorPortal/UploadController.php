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
     * @return Response|ResponseFactory
     */
    public function upload(StoreUploadRequest $request, PurchaseOrder $purchase_order)
    {

        $this->saveDocuments($request->getFile(), $purchase_order, true);

        return response([], 200);
    }
}
