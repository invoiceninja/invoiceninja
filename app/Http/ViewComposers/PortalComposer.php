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
    public function compose(View $view) :void
    {
       $view->with('portal', $this->portalData());
    }

    /**
     * @return array
     */
    private function portalData() :array
    {
        if(!auth()->user())
            return [];

        $data['sidebar'] = $this->sidebarMenu();
        $data['header'] = [];
        $data['footer'] = [];

        
        return $data;
    }

    private function sidebarMenu() :array
    {

        $data = [];

        $data[] = [ 'title' => ctrans('texts.dashboard'), 'url' => 'client.dashboard', 'icon' => 'fa fa-tachometer'];
        $data[] = [ 'title' => ctrans('texts.invoices'), 'url' => 'client.invoices.index', 'icon' => 'fa fa-file-excel-o'];

        return $data;
    }

}