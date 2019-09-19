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

use App\Utils\TranslationHelper;
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

       $view->with($this->portalData());

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
        $data['countries'] = TranslationHelper::getCountries();
        $data['company'] = auth()->user()->company;
        $data['client'] = auth()->user()->client;

        return $data;

    }

    private function sidebarMenu() :array
    {

        $data = [];

        $data[] = [ 'title' => ctrans('texts.dashboard'), 'url' => 'client.dashboard', 'icon' => 'fa fa-tachometer fa-fw fa-2x'];
        $data[] = [ 'title' => ctrans('texts.invoices'), 'url' => 'client.invoices.index', 'icon' => 'fa fa-file-pdf-o fa-fw fa-2x'];
        $data[] = [ 'title' => ctrans('texts.recurring_invoices'), 'url' => 'client.recurring_invoices.index', 'icon' => 'fa fa-files-o fa-fw fa-2x'];
        $data[] = [ 'title' => ctrans('texts.payments'), 'url' => 'client.payments.index', 'icon' => 'fa fa-credit-card fa-fw fa-2x'];
        $data[] = [ 'title' => ctrans('texts.payment_methods'), 'url' => 'client.payment_methods.index', 'icon' => 'fa fa-cc-stripe fa-fw fa-2x'];

        return $data;
        
    }

}