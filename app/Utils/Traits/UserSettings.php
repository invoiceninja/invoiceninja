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

namespace App\Utils\Traits;

use stdClass;

/**
 * Class UserSettings.
 */
trait UserSettings
{
    /**
     * @param string $entity
     * @return stdClass
     */
    public function getEntity(string $entity): stdClass
    {
        return $this->settings()->{$entity};
    }

    /**
     * @param string $entity
     * @return stdClass
     */
    public function getColumnVisibility(string $entity): stdClass
    {
        return $this->settings()->{class_basename($entity)}->datatable->column_visibility;
    }
}
