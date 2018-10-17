<?php

// Home
Breadcrumbs::for('dashboard', function ($trail) {
    $trail->push(trans('texts.dashboard'), route('user.dashboard'));
});