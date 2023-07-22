<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Utils\Ninja;
use Illuminate\Http\Request;
use App\Jobs\Util\UnlinkFile;
use App\Exceptions\SystemError;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ProtectedDownloadController extends BaseController
{

    public function index(Request $request)
    {

        $hashed_path = Cache::pull($request->hash);
        
        if (!$hashed_path) {
            throw new SystemError('File no longer available', 404);
            abort(404, 'File no longer available');
        }
        
        return response()->streamDownload(function () use ($hashed_path) {
            echo Storage::get($hashed_path);
        }, basename($hashed_path), []);

    }

}
