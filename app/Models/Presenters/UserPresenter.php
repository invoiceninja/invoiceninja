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

namespace App\Models\Presenters;

/**
 * Class UserPresenter.
 */
class UserPresenter extends EntityPresenter
{
    /**
     * @return string
     */
    public function name()
    {
        if (! $this->entity) {
            return 'No User Object Available';
        }

        $first_name = isset($this->entity->first_name) ? $this->entity->first_name : '';
        $last_name = isset($this->entity->last_name) ? $this->entity->last_name : '';

        return $first_name.' '.$last_name;
    }

    public function getDisplayName()
    {
        if ($this->getFullName()) {
            return $this->getFullName();
        } elseif ($this->entity->email) {
            return $this->entity->email;
        } else {
            return ctrans('texts.guest');
        }
    }

    /**
     * @return string
     */
    public function getFullName()
    {
        if ($this->entity->first_name || $this->entity->last_name) {
            return $this->entity->first_name.' '.$this->entity->last_name;
        } else {
            return '';
        }
    }
}
