<?php

// Dashboard
Breadcrumbs::for('dashboard', function ($trail) {
    $trail->push(trans('texts.dashboard'), route('dashboard.index'));
});

// Dashboard > Client
Breadcrumbs::for('clients', function ($trail) {
    $trail->parent('dashboard');
    $trail->push(trans('texts.clients'), route('clients.index'));
});

Breadcrumbs::for('clients.edit', function($trail, $client) {
    $trail->parent('clients');
    $trail->push($client->name, route('clients.edit', $client));
});
