<?php

// Dashboard
Breadcrumbs::for('dashboard', function ($trail) {
    $trail->push(trans('texts.dashboard'), route('client.dashboard'));
});

// Invoices
Breadcrumbs::for('invoices', function ($trail) {
    $trail->push(ctrans('texts.invoices'), route('client.invoices.index'));
});

// Invoices > Show invoice
Breadcrumbs::for('invoices.show', function ($trail, $invoice) {
    $trail->parent('invoices');
    $trail->push(sprintf('%s: %s', ctrans('texts.invoice'), $invoice->number), route('client.invoices.index', $invoice->hashed_id));
});

// Recurring invoices
Breadcrumbs::for('recurring_invoices', function ($trail) {
    $trail->push(ctrans('texts.recurring_invoices'), route('client.recurring_invoices.index'));
});

// Recurring invoices > Show recurring invoice
Breadcrumbs::for('recurring_invoices.show', function ($trail, $invoice) {
    $trail->parent('recurring_invoices');
    $trail->push(sprintf('%s: %s', ctrans('texts.recurring_invoice'), $invoice->hashed_id), route('client.recurring_invoices.index', $invoice->hashed_id));
});

// Recurring invoices > Show recurring invoice
Breadcrumbs::for('recurring_invoices.request_cancellation', function ($trail, $invoice) {
    $trail->parent('recurring_invoices.show', $invoice);
    $trail->push(ctrans('texts.request_cancellation'), route('client.recurring_invoices.request_cancellation', $invoice->hashed_id));
});

// Payments
Breadcrumbs::for('payments', function ($trail) {
    $trail->push(ctrans('texts.payments'), route('client.payments.index'));
});

// Payments > Show payment
Breadcrumbs::for('payments.show', function ($trail, $invoice) {
    $trail->parent('payments');
    $trail->push(sprintf('%s: %s', ctrans('texts.payment'), $invoice->hashed_id), route('client.payments.index', $invoice->hashed_id));
});

// Payment methods
Breadcrumbs::for('payment_methods', function ($trail) {
    $trail->push(ctrans('texts.payment_methods'), route('client.payment_methods.index'));
});

// Payment methods > Show payment method
Breadcrumbs::for('payment_methods.show', function ($trail, $invoice) {
    $trail->parent('payment_methods');
    $trail->push(sprintf('%s: %s', ctrans('texts.payment_methods'), $invoice->hashed_id), route('client.payment_methods.index', $invoice->hashed_id));
});

// Dashboard > Client
Breadcrumbs::for('clients', function ($trail) {
    $trail->parent('dashboard');
    $trail->push(trans('texts.clients'), route('clients.index'));
});

Breadcrumbs::for('clients.show', function ($trail, $client) {
    $trail->parent('clients');
    $trail->push($client->name, route('clients.show', $client));
});

Breadcrumbs::for('clients.edit', function ($trail, $client) {
    $trail->parent('clients');
    $trail->push($client->name, route('clients.edit', $client));
});

Breadcrumbs::for('clients.create', function ($trail) {
    $trail->parent('clients');
});

