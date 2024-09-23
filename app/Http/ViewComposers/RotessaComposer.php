<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

use Illuminate\Support\Facades\View;
use App\DataProviders\CAProvinces;
use App\DataProviders\USStates;

View::composer(['*.rotessa.components.address','*.rotessa.components.banks.US.bank','*.rotessa.components.dropdowns.country.US'], function ($view) {
    $states = USStates::get();
    $view->with('states', $states);
});

View::composer(['*.rotessa.components.address','*.rotessa.components.banks.CA.bank','*.rotessa.components.dropdowns.country.CA'], function ($view) {
    $provinces = CAProvinces::get();
    $view->with('provinces', $provinces);
});
