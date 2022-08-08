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

namespace App\Helpers\Document;

trait WithTypeHelpers
{
    /**
     * Returns boolean based on checks for image.
     *
     * @return bool
     */
    public function isImage(): bool
    {
        if (in_array($this->type, ['png', 'jpeg', 'jpg', 'tiff', 'gif'])) {
            return true;
        }

        return false;
    }
}
