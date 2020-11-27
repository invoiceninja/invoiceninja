<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;

use App\Models\Document;
use App\Utils\Ninja;

/**
 * Class for document repository.
 */
class DocumentRepository extends BaseRepository
{
    public function delete($document)
    {
        $document->deleteFile();
        $document->forceDelete();
    }

    public function restore($document)
    {
        if (! $document->trashed()) {
            return;
        }

        $document->restore();

        // if (class_exists($className)) {
        //     event(new $className($document, $document->company, Ninja::eventVars()));
        // }
    }
}
