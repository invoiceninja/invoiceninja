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

trait WithSorting
{
    public $sort_field = 'id'; // Default sortBy. Feel free to change or pull from client/company settings.
    public $sort_asc = true;

    public function sortBy($field)
    {
        $this->sort_field === $field
            ? $this->sort_asc = ! $this->sort_asc
            : $this->sort_asc = true;

        $this->sort_field = $field;
    }
}
