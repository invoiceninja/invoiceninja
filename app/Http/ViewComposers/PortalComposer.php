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

namespace App\Http\ViewComposers;

use App\Utils\Ninja;
use App\Utils\TranslationHelper;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Illuminate\View\View;

/**
 * Class PortalComposer.
 */
class PortalComposer
{
    public const MODULE_RECURRING_INVOICES = 1;

    public const MODULE_CREDITS = 2;

    public const MODULE_QUOTES = 4;

    public const MODULE_TASKS = 8;

    public const MODULE_EXPENSES = 16;

    public const MODULE_PROJECTS = 32;

    public const MODULE_VENDORS = 64;

    public const MODULE_TICKETS = 128;

    public const MODULE_PROPOSALS = 256;

    public const MODULE_RECURRING_EXPENSES = 512;

    public const MODULE_RECURRING_TASKS = 1024;

    public const MODULE_RECURRING_QUOTES = 2048;

    public const MODULE_INVOICES = 4096;

    public const MODULE_PROFORMAL_INVOICES = 8192;

    public const MODULE_PURCHASE_ORDERS = 16384;

    public $settings;

    /**
     * Bind data to the view.
     *
     * @param  View  $view
     * @return void
     */
    public function compose(View $view) :void
    {
        $view->with($this->portalData());

        if (auth()->guard('contact')->user()) {
            App::forgetInstance('translator');
            $t = app('translator');
            $t->replace(Ninja::transformTranslations(auth()->guard('contact')->user()->client->getMergedSettings()));
        }
    }

    /**
     * @return array
     */
    private function portalData() :array
    {
        if (! auth()->guard('contact')->user()) {
            return [];
        }

        $this->settings = auth()->guard('contact')->user()->client->getMergedSettings();

        $data['sidebar'] = $this->sidebarMenu();
        $data['header'] = [];
        $data['footer'] = [];
        $data['countries'] = TranslationHelper::getCountries();
        $data['company'] = auth()->guard('contact')->user()->company;
        $data['client'] = auth()->guard('contact')->user()->client;
        $data['settings'] = $this->settings;
        $data['currencies'] = TranslationHelper::getCurrencies();
        $data['contact'] = auth()->guard('contact')->user();

        $data['multiple_contacts'] = session()->get('multiple_contacts') ?: collect();

        return $data;
    }

    private function sidebarMenu() :array
    {
        $enabled_modules = auth()->guard('contact')->user()->company->enabled_modules;
        $data = [];

        // TODO: Enable dashboard once it's completed.
        // $this->settings->enable_client_portal_dashboard
        // $data[] = [ 'title' => ctrans('texts.dashboard'), 'url' => 'client.dashboard', 'icon' => 'activity'];

        if (self::MODULE_INVOICES & $enabled_modules) {
            $data[] = ['title' => ctrans('texts.invoices'), 'url' => 'client.invoices.index', 'icon' => 'file-text'];
        }

        if (self::MODULE_RECURRING_INVOICES & $enabled_modules) {
            $data[] = ['title' => ctrans('texts.recurring_invoices'), 'url' => 'client.recurring_invoices.index', 'icon' => 'file'];
        }

        $data[] = ['title' => ctrans('texts.payments'), 'url' => 'client.payments.index', 'icon' => 'credit-card'];

        if (self::MODULE_QUOTES & $enabled_modules) {
            $data[] = ['title' => ctrans('texts.quotes'), 'url' => 'client.quotes.index', 'icon' => 'align-left'];
        }

        if (self::MODULE_CREDITS & $enabled_modules) {
            $data[] = ['title' => ctrans('texts.credits'), 'url' => 'client.credits.index', 'icon' => 'credit-card'];
        }

        $data[] = ['title' => ctrans('texts.payment_methods'), 'url' => 'client.payment_methods.index', 'icon' => 'shield'];
        $data[] = ['title' => ctrans('texts.documents'), 'url' => 'client.documents.index', 'icon' => 'download'];

        if (auth()->guard('contact')->user()->client->getSetting('enable_client_portal_tasks')) {
            $data[] = ['title' => ctrans('texts.tasks'), 'url' => 'client.tasks.index', 'icon' => 'clock'];
        }

        $data[] = ['title' => ctrans('texts.statement'), 'url' => 'client.statement', 'icon' => 'activity'];

        if (Ninja::isHosted() && auth()->guard('contact')->user()->company->id == config('ninja.ninja_default_company_id')) {
            $data[] = ['title' => ctrans('texts.plan'), 'url' => 'client.plan', 'icon' => 'credit-card'];
        } else {
            $data[] = ['title' => ctrans('texts.subscriptions'), 'url' => 'client.subscriptions.index', 'icon' => 'calendar'];
        }

        return $data;
    }
}
