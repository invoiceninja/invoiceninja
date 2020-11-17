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

namespace App\Http\ViewComposers;

use App\Models\ClientContact;
use App\Utils\Ninja;
use App\Utils\TranslationHelper;
use Illuminate\Support\Facades\Lang;
use Illuminate\View\View;

/**
 * Class PortalComposer.
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

        if(auth()->user())
            Lang::replace(Ninja::transformTranslations(auth()->user()->client->getMergedSettings()));
    }

    /**
     * @return array
     */
    private function portalData() :array
    {
        if (! auth()->user()) {
            return [];
        }

        $data['sidebar'] = $this->sidebarMenu();
        $data['header'] = [];
        $data['footer'] = [];
        $data['countries'] = TranslationHelper::getCountries();
        $data['company'] = auth()->user()->company;
        $data['client'] = auth()->user()->client;
        $data['settings'] = auth()->user()->client->getMergedSettings();
        $data['currencies'] = TranslationHelper::getCurrencies();

        $data['multiple_contacts'] = ClientContact::where('email', auth('contact')->user()->email)->whereNotNull('email')->distinct('company_id')->get();

        return $data;
    }

    private function sidebarMenu() :array
    {
        $data = [];

        // $data[] = [ 'title' => ctrans('texts.dashboard'), 'url' => 'client.dashboard', 'icon' => 'activity'];
        $data[] = ['title' => ctrans('texts.invoices'), 'url' => 'client.invoices.index', 'icon' => 'file-text'];
        $data[] = ['title' => ctrans('texts.recurring_invoices'), 'url' => 'client.recurring_invoices.index', 'icon' => 'file'];
        $data[] = ['title' => ctrans('texts.payments'), 'url' => 'client.payments.index', 'icon' => 'credit-card'];
        $data[] = ['title' => ctrans('texts.quotes'), 'url' => 'client.quotes.index', 'icon' => 'align-left'];
        $data[] = ['title' => ctrans('texts.credits'), 'url' => 'client.credits.index', 'icon' => 'credit-card'];
        $data[] = ['title' => ctrans('texts.payment_methods'), 'url' => 'client.payment_methods.index', 'icon' => 'shield'];
        $data[] = ['title' => ctrans('texts.documents'), 'url' => 'client.documents.index', 'icon' => 'download'];

        if (auth()->user('contact')->client->getSetting('enable_client_portal_tasks')) {
            $data[] = ['title' => ctrans('texts.tasks'), 'url' => 'client.dashboard', 'icon' => 'clock']; 
            
            // TODO: Update when 'tasks' module is available in client portal.
        }

        return $data;
    }
}
