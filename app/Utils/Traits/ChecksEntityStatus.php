<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils\Traits;

/**
 * Class ChecksEntityStatus.
 */
trait ChecksEntityStatus
{
    public function entityIsDeleted($entity)
    {
        return $entity->is_deleted;
    }

    public function disallowUpdate()
    {
        return response()->json(['message'=>'Record is deleted and cannot be edited. Restore the record to enable editing'], 400);
    }
}
