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

namespace App\Http\ViewComposers;

use Illuminate\View\View;

/**
 * Class PortalComposer
 * @package App\Http\ViewComposers
 */
class PortalComposer
{

    /**
     * Bind data to the view.
     *
     * @param  View  $view
     * @return void
     */
    public function compose(View $view)
    {
        $view->with('header', $this->portalData());
    }

    /**
     * @return array
     */
    private function portalData()
    {
        if(!auth()->user())
            return [];

        $data = [];

        
        return $data;
    }

}