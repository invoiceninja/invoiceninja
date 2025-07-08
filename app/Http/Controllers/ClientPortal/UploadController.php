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

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\Uploads\StoreUploadRequest;
use App\Utils\Traits\SavesDocuments;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;

class UploadController extends Controller
{
    use SavesDocuments;

    /**
     * Main logic behind uploading the files.
     *
     * @param StoreUploadRequest $request
     * @return Response| \Illuminate\Http\JsonResponse|ResponseFactory
     */
    public function __invoke(StoreUploadRequest $request)
    {

        /** @var \App\Models\ClientContact $client_contact **/
        $client_contact = auth()->user();

        $this->saveDocuments($request->getFile(), $client_contact->client, $request->input('is_public', true));

        return response([], 200);
    }
}
