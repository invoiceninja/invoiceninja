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

namespace App\Models;

class Backup extends BaseModel
{
    public function getEntityType()
    {
        return self::class;
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }
}
