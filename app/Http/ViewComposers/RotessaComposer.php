<?php

use Illuminate\Support\Facades\View;
use App\DataProviders\CAProvinces;
use App\DataProviders\USStates;

View::composer(['*.rotessa.components.address','*.rotessa.components.banks.US.bank','*.rotessa.components.dropdowns.country.US'], function ($view) {
    $states = USStates::get();
    $view->with('states', $states);
});

// CAProvinces View Composer
View::composer(['*.rotessa.components.address','*.rotessa.components.banks.CA.bank','*.rotessa.components.dropdowns.country.CA'], function ($view) {
    $provinces = CAProvinces::get();
    $view->with('provinces', $provinces);
});