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

namespace App\Utils\Traits;

trait Archivable
{
    /**
     * Archive the entity. Set archived_at to timestamp.
     *
     * @return void
     */
    public function archive()
    {
        $this->archived_at = now();
        $this->save();
    }

    /**
     * Unarchive the entity. Set archived_at to null.
     *
     * @return void
     */
    public function unarchive()
    {
        $this->archived_at = null;
        $this->save();
    }
}
