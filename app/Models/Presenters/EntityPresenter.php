<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models\Presenters;

use App\Utils\Traits\MakesHash;
use Hashids\Hashids;
use Laracasts\Presenter\Presenter;
use URL;
use Utils;
use stdClass;

/**
 * Class EntityPresenter
 * @package App\Models\Presenters
 */
class EntityPresenter extends Presenter
{
    use MakesHash;

    /**
     * @return string
     */
    public function id()
    {
        return $this->encodePrimaryKey($this->entity->id);
    }

    /**
     *
     */
    public function url()
    {

    }

    /**
     *
     */
    public function path()
    {

    }

    /**
     *
     */
    public function editUrl()
    {
    }

    /**
     * @param bool $label
     */
    public function statusLabel($label = false)
    {

    }

    /**
     *
     */
    public function statusColor()
    {

    }

    /**
     *
     */
    public function link()
    {

    }

    /**
     *
     */
    public function titledName()
    {

    }

    /**
     * @param bool $subColors
     */
    public function calendarEvent($subColors = false)
    {

    }

}
