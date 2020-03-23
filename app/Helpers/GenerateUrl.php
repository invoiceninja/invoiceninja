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

use App\Models\Document;
use Illuminate\Support\Facades\Storage;

/**
  * Generate url for the asset.
  *
  * @param Document $document
  * @param boolean $absolute
  * @return string|null
  */
function generateUrl(Document $document, $absolute = false)
{
    $url = Storage::disk($document->disk)->url($document->path);

    if ($url && $absolute) {
        return url($url);
    }

    if ($url) {
        return $url;
    }

    return null;
}
